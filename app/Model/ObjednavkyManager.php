<?php
/******************************************************************************************************************
 * 
 * Tento model slouží pro jednotné místo načítání objednávek z databáze pro presentery.
 * Postupně sem budou přesunuta všechna volání databáze z presenterů, především metody mapRozpocet()
 * 
 * Prvním krokem je přesunutí všech metod mapRozpocet a jejich přejmenování na mapRozpocetNazevPresenteru().
 * Následně přijde na řadu sjednocení funkcionality.
 * 
 */

namespace App\Model;

use Exception;
use Nette;

class ObjednavkyManager
{
	use Nette\SmartObject;

	/** @var Nette\Database\Context */
	private $database;

	public $fmt;

	public function __construct(Nette\Database\Context $database) {
		$this->database = $database;
		$this->fmt = new \NumberFormatter( 'cs_CZ', \NumberFormatter::CURRENCY );
		$this->fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
	}

	/**
	 * Načte tabulku pro MojeObjednavkyPresenter podle stavů
	 */
    public function mapRozpocetMojeObjednavky(int $zadavatel, array $stavy, $rok) : array {
        $source = $this->objednavkyPodleStavu($stavy, $rok)
						->where('zakladatel', $zadavatel)
						->order('id DESC');
		return $this->mapRozpocetFromSource($source);
    }
	
	/**
	 * Načte tabulku pro MojeObjednavkyPresenter podle stavů
	 */
    public function mapRozpocetVsechnyMojeObjednavky(int $zadavatel, $rok) : array {
        $source = $this->objednavkyPodleVlastnika($zadavatel, $rok)
						->order('id DESC');
		return $this->mapRozpocetFromSource($source);
    }
	
	public function mapRozpocetPrehled(array $stavy, $rok) : array {
		$source = $this->objednavkyPodleStavu($stavy, $rok)
						->order('id DESC');
		return $this->mapRozpocetFromSource($source);
	}
    
	public function mapObjednavkyRozpocetStav(int $rozpocet_id, array $stavy, $rok) : array {
		$source = $this->objednavkyPodleRozpoctu($rozpocet_id, $rok)
						->where('stav', $stavy)
						->order('id DESC');
		return $this->mapRozpocetFromSource($source);
	}
    

	/**
	 * z připraveného datasource naplní pole rozpočtů pro gridy v prezenteru
	 */
    private function mapRozpocetFromSource($source) : array {
        $fetchedRozpocets = [];
        foreach ($source as $objednavky) {
            $item = [
            	'id' => $objednavky->id,
            	'id_prehled' => $objednavky->id_prehled,
            	'radka' => $objednavky->radka,
	            //$pomoc =  $this->database->table('objednavky')->where('id_prehled',$item->id_prehled )->fetch();
    	        // $pom2 = $this->database->table('prehled')->where('id',$pomoc->id_prehled)->fetch();
        	    // $pom3 = $this->database->table('uzivatel')->where('id',$pom2->id_uzivatel)->fetch();
            	// $item->zadavatel =$pom3->jmeno ;
            	'zadavatel' => $objednavky->ref('zakladatel')->jmeno,
				'zalozil' => $objednavky->zalozil,
            	'schvalovatel' => $objednavky->ref('kdo')->jmeno,
            	'schvalil' => $objednavky->schvalil,
            	'overovatel' => $objednavky->ref('kdo2')->jmeno,
            	'overil' => $objednavky->overil,
            	'nutno_overit' => $objednavky->nutno_overit,
            	'stav' => $objednavky->stav,
            	'stavPopis' => $objednavky->ref('stav')->popis,
            	'firma' => $objednavky->firma,
            	'popis' => $objednavky->popis,
            	'cinnost' => $objednavky->ref('cinnost')->cinnost,
            	'cinnostP' => $objednavky->ref('cinnost')->nazev_cinnosti,
            	'zakazka' => $objednavky->ref('zakazka')->zakazka,
            	'stredisko' => $objednavky->ref('stredisko')->stredisko,
				'castka' => $objednavky->castka,
			];
			$fetchedRozpocets[] = $item;
		}
        return $fetchedRozpocets;
	}


	public function mapObjednavka(int $prehledId) : array {
		$source = $this->database->table('objednavky')
					->where('id_prehled', $prehledId);
		return $this->mapRozpocetFromSource($source);
	}


	/**
	 * Smaže objednávky, resp. změní jejich stav v databázi na smazané
	 */
	public function smazObjednavkyDb(array $ids): void {
        // TODO: tady je nutne doplnit kod kontrolujici, zda ma uzivatel pravo objednavku smazat

        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 7
        ]);
	}
	
	
    public function mapPrehledObjednavek(bool $smazane, int $rok) : array{
		if (isset($rok)) {
			$letosni = $this->database->table('objednavky')->select('MAX(id_prehled) AS pr')->where('cinnost.rok',$rok)->group('id_prehled')->fetchPairs('pr','pr');
			$source = $this->database->table('prehled')
							->where('id IN ?',$letosni)
							->order('id DESC');
		} else {
			$source = $this->database->table('prehled')
							->order('id DESC');
		}
		return $this->mapPrehledObjednavekFromSource($source, $smazane, $rok);
    }

	/** 
	 * vytvori novou verzi rozpoctu v zadanem roce kopii ze zadane verze
	 * @param $rok rok, ve kterem se ma vytvorit nova verze
	 * @param $verze zdrojova verze, jejiz kopie se vytvori; neni-li zadana, pouzije se posledni v danem roce
	 * @return $novaVerze cislo nove vytvorene verze nebo 0 pokud nastane chyba
	 */
	public function vytvorNovouVerziRozpoctu(int $rok, int $verze = null): int {
		$novaVerze = 0;
		try {
			if (empty($verze)) {
				$verze = $this->database->table('rozpocet')->where('rok',$rok)->max('verze');
			}

			// kopie rozpoctu a cinnosti v transakci
			$novaVerze = $this->database->transaction(function() use ($rok, $verze) {
				$novaVerze = $verze + 1;
				$rozpocty = $this->database->table('rozpocet')->where('rok',$rok)->where('verze',$verze);
				foreach ($rozpocty as $rozpocet) {
					// vytvor kopii rozpoctu
					$novyRozpocet = $this->database->table('rozpocet')->insert([
						'rozpocet' => $rozpocet->rozpocet,
						'hospodar' => $rozpocet->hospodar,
						'hospodar2' => $rozpocet->hospodar2,
						'rok' => $rozpocet->rok,
						'verze' => $novaVerze,
						'castka' => $rozpocet->castka,
						'sablony' => $rozpocet->sablony,
						'overeni' => $rozpocet->overeni,
						'overovatel' => $rozpocet->overovatel,
						'hezky' => $rozpocet->hezky,
						'obsah' => $rozpocet->obsah,
					]);
					// preved vsechny cinnosti stareho rozpoctu na novy rozpocet
					$this->database->table('cinnost')->where('id_rozpocet',$rozpocet->id)->update([
						'id_rozpocet' => $novyRozpocet,
					]);
					// preved vsechna sledovani tohoto rozpoctu na novy
					$this->database->table('skupiny')->where('rozpocet',$rozpocet->id)->update([
						'rozpocet' => $novyRozpocet,
					]);
				}

				// povedlo se zkopirovat rozpocet, nastav novou verzi jako aktualni
				$this->database->table('setup')->where('id', 1)->update([
					'verze' => $novaVerze,
				]);

				return $novaVerze;
			});

		} catch (Exception $e) {
			bdump($e);
			return 0;
		}
		return $novaVerze;
	}

	
	/** 
	 * vytvori rozpocet pro novy rok kopii ze zadaneho roku a verze; novy rok ma vzdy o 1 vyssi cislo nez posledni existujici rok
	 * @param $rok rok, ze ktereho se ma vytvorit nova verze
	 * @param $verze zdrojova verze, jejiz kopie se vytvori; neni-li zadana, pouzije se posledni v danem roce
	 * @return $novaVerze cislo nove vytvorene verze nebo 0 pokud nastane chyba
	 */
	public function vytvorNovyRokRozpoctu(int $rok, int $verze = null): int {
		$novyRok = 0;
		try {
			if (empty($verze)) {
				$verze = $this->database->table('rozpocet')->where('rok',$rok)->max('verze');
			}
				$novyRok = $this->database->table('rozpocet')->max('rok') + 1;
			// kopie rozpoctu a cinnosti v transakci
			$novaVerze = $this->database->transaction(function() use ($rok, $verze, $novyRok) {
				
				$rozpocty = $this->database->table('rozpocet')->where('rok',$rok)->where('verze',$verze);
				foreach ($rozpocty as $rozpocet) {
					// vytvor kopii rozpoctu
					$novyRozpocet = $this->database->table('rozpocet')->insert([
						'rozpocet' => $rozpocet->rozpocet,
						'hospodar' => $rozpocet->hospodar,
						'hospodar2' => $rozpocet->hospodar2,
						'rok' => $novyRok,
						'verze' => 1,
						'castka' => $rozpocet->castka,
						'sablony' => $rozpocet->sablony,
						'overeni' => $rozpocet->overeni,
						'overovatel' => $rozpocet->overovatel,
						'hezky' => $rozpocet->hezky,
						'obsah' => $rozpocet->obsah,
					]);
					// kopie cinnosti stareho roku do noveho
					$cinnosti = $this->database->table('cinnost')->where('id_rozpocet',$rozpocet->id);
					foreach ($cinnosti as $cinnost) {
						$novaCinnost = $this->database->table('cinnost')->insert([
							'cinnost' => $cinnost->cinnost,
							'nazev_cinnosti' => $cinnost->nazev_cinnosti,
							'rok' => $novyRok,
							'vyber' => $cinnost->vyber,
							'id_rozpocet' => $novyRozpocet,
						]);
					}
					// kopie skupin sledovani stareho roku do noveho
					$skupiny = $this->database->table('skupiny')->where('rozpocet',$rozpocet->id);
					foreach ($skupiny as $skupina) {
						$novaCinnost = $this->database->table('skupiny')->insert([
							'uzivatel' => $skupina->uzivatel,
							'rozpocet' => $novyRozpocet,
						]);
					}
				}

				return $novyRok;
			});

		} catch (Exception $e) {
			bdump($e);
			return 0;
		}
		return $novaVerze;
	}

	public function pridejLimitRozpoctu(array $limityRozpoctu, int $cinnostId, int $zakazkaId, ?int $castkaVlastni, ?int $castkaSablony): array {
		
		$cinnost = $this->database->table('cinnost')->where('id',$cinnostId)->fetch();
		$zakazka = $this->database->table('zakazky')->where('id',$zakazkaId)->fetch();

		$relevantniV = $this->database->table('zakazky')->where('vlastni', 1)->select('zakazka'); 
        $relevantniS = $this->database->table('zakazky')->where('sablony', 1)->select('zakazka'); 

		$castkaRozpoctuV = $cinnost->rozpocet->castka;
		$castkaRozpoctuS = $cinnost->rozpocet->sablony;

		if (!array_key_exists($cinnost->id_rozpocet, $limityRozpoctu)) {
			$objednanoV = $this->database->table('objednavky')->where('cinnost.id_rozpocet', $cinnost->id_rozpocet)->where('zakazka.vlastni', 1)
				->where('stav',[0,1,3,4,9])->sum('castka');
			$denikV = $this->database->table('denik')->where('rozpocet', $cinnost->id_rozpocet)->where('zakazky',$relevantniV)
				->where('petky',1)->sum('castka');
			$maxCastkaV = round($castkaRozpoctuV - ($objednanoV + $denikV));

			$objednanoS = $this->database->table('objednavky')->where('cinnost.id_rozpocet', $cinnost->id_rozpocet)->where('zakazka.sablony', 1)
				->where('stav',[0,1,3,4,9])->sum('castka');
			$denikS = $this->database->table('denik')->where('rozpocet', $cinnost->id_rozpocet)->where('zakazky',$relevantniS)
				->where('petky',1)->sum('castka');
			$maxCastkaS = round($castkaRozpoctuS - ($objednanoS + $denikS));

			$limityRozpoctu[$cinnost->id_rozpocet] = [
				'nazevRozpoctu' => $cinnost->rozpocet->rozpocet,
				'castkaRozpoctuV' => $castkaRozpoctuV, 
				'castkaRozpoctuS' => $castkaRozpoctuS, 
				'objednanoV' => $objednanoV,
				'objednanoS' => $objednanoS,
				'denikV' => $denikV,
				'denikS' => $denikS,
				'limitV' => $maxCastkaV,
				'limitS' => $maxCastkaS,
				'pozadovanoVlastni' => $castkaVlastni,
				'pozadovanoSablony' => $castkaSablony,
				'pozadovanoCelkem' => $castkaVlastni+$castkaSablony,
				'overeni' => $cinnost->rozpocet->overeni,
				'kdoma' => $cinnost->rozpocet->hospodar,
				'kdoma2'=> $cinnost->rozpocet->overovatel,
			];
		} else {
			$limityRozpoctu[$cinnost->id_rozpocet]['pozadovanoVlastni'] += $castkaVlastni;
			$limityRozpoctu[$cinnost->id_rozpocet]['pozadovanoSablony'] += $castkaSablony;
			$limityRozpoctu[$cinnost->id_rozpocet]['pozadovanoCelkem'] += $castkaVlastni+$castkaSablony;
		}
		return $limityRozpoctu;
	}





	/**
	 * z připraveného datasource naplní pole rozpočtů pro gridy v prezenteru
	 */
    private function mapPrehledObjednavekFromSource($source, bool $smazane, int $rok) : array {
        $fetchedPrehled = [];
        foreach ($source as $prehled) {
			$item = [
            	'id' => $prehled->id,
				'zadavatel' => $prehled->ref('zakladatel') == null ? '' : $prehled->ref('zakladatel')->jmeno,
				'zalozil' => $prehled->zalozil,
				'popis' => $prehled->popis,
				'rok' => $rok, //TK TODO tady musí být rok převzatý z tabulky, aby bylo možné listovat objednávky přes více let
			];
			$objednavky = $this->sumaObjednavekPodlePrehleduAStavu($prehled->id, $rok);
			if (isset($objednavky)) {
				$item = array_merge($item, $objednavky->toArray());
			} else {
				// k objednavce se nena
				$item = array_merge($item, [
					'cinnosti' => '',
					'firma' => '',
					'castka_neschvalene' => 0,
					'pocet_neschvalene' => 0,
					'castka_schvalene' => 0,
					'pocet_schvalene' => 0,
					'castka_zamitnute' => 0,
					'pocet_zamitnute' => 0,
					'castka_uctarna' => 0,
					'pocet_uctarna' => 0,
					'castka_celkem' => 0,
					'pocet_celkem' => 0,
				]);
			}
			if ($item['pocet_celkem']>0 || $smazane) {
				//uloz jen pokud je nenulovy pocet polozek (krome stavu 6 = archiv)
				$fetchedPrehled[] = $item;
			}
		}
        return $fetchedPrehled;
	}


	private function objednavkyPodleStavu(array $stavy, int $rok) {
		//$rok = $this->database->table('setup')->get(1)->rok; //TODO brat rok ze session
		return $this->database->table('objednavky')
						->where('stav', $stavy)
						->where('cinnost.rozpocet.rok', $rok);
	}

	private function objednavkyPodleVlastnika(int $user_id, int $rok) {
		//$rok = $this->database->table('setup')->get(1)->rok; //TODO brat rok ze session
		return $this->database->table('objednavky')
						->where('zakladatel', $user_id)
						->where('cinnost.rozpocet.rok', $rok);
	}

	private function objednavkyPodleRozpoctu(int $rozpocet_id) {
		return $this->database->table('objednavky')
						->where('cinnost.id_rozpocet', $rozpocet_id);
	}

	private function sumaObjednavekPodlePrehleduAStavu(int $prehledId, int $rok) {
		//$rok = $this->database->table('setup')->get(1)->rok;
		return $this->database->table('objednavky')
				->select("SUM(CASE WHEN stav IN (0,1,2,3,4,5,8,9) THEN objednavky.castka ELSE 0 END) AS castka_celkem, "
					."COUNT(CASE WHEN stav IN (0,1,2,3,4,5,8,9) THEN objednavky.id ELSE NULL END) AS pocet_celkem, "
					."SUM(CASE WHEN stav IN (0,1) THEN objednavky.castka ELSE 0 END) AS castka_neschvalene, "
					."COUNT(CASE WHEN stav IN (0,1) THEN objednavky.id ELSE NULL END) AS pocet_neschvalene, "
					."SUM(CASE WHEN stav IN (3,4) THEN objednavky.castka ELSE 0 END) AS castka_schvalene, "
					."COUNT(CASE WHEN stav IN (3,4) THEN objednavky.id ELSE NULL END) AS pocet_schvalene, "
					."SUM(CASE WHEN stav IN (2,5,8) THEN objednavky.castka ELSE 0 END) AS castka_zamitnute, "
					."COUNT(CASE WHEN stav IN (2,5,8) THEN objednavky.id ELSE NULL END) AS pocet_zamitnute, "
					."SUM(CASE WHEN stav IN (9) THEN objednavky.castka ELSE 0 END) AS castka_uctarna, "
					."COUNT(CASE WHEN stav IN (9) THEN objednavky.id ELSE NULL END) AS pocet_uctarna, "
					."GROUP_CONCAT(DISTINCT cinnost.cinnost SEPARATOR ', ') AS cinnosti, "
					."GROUP_CONCAT(DISTINCT objednavky.firma SEPARATOR ', ') AS firma ")
				->group('id_prehled')->where('id_prehled',$prehledId)
//				->where('cinnost.rozpocet.rok', $rok)   //TK: neni potreba, navic hazi chybu v testovaci DB kde se obcas objednavka odkazuje na cinnosti z jineho roku
				->fetch();
	}
}