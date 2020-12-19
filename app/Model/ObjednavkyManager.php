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
	 * Načte tabulku pro MojeObjednavkyPresenter
	 */
    public function mapRozpocetMojeObjednavky($zadavatel, $stavy) {
        $source = $this->database->table('objednavky')
						->where('zakladatel', $zadavatel)
						->where('stav', $stavy)
						->order('id DESC');
		return $this->mapRozpocetFromSource($source);
    }
	
	public function mapRozpocetPrehled($aray)
    {
		$source = $this->database->table('objednavky')
						->where('stav', $aray)
						->order('id DESC');
		return $this->mapRozpocetFromSource($source);
	}
    

	/**
	 * z připraveného datasource naplní pole rozpočtů pro gridy v prezenteru
	 */
    private function mapRozpocetFromSource($source) {
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
            	'schvalovatel' => $objednavky->ref('kdo')->jmeno,
            	'schvalil' => $objednavky->schvalil,
            	'overovatel' => $objednavky->ref('kdo2')->jmeno,
            	'overil' => $objednavky->overil,
            	'nutno_overit' => $objednavky->ref('nutno_overit')->popis,
            	'stav' => $objednavky->ref('stav')->popis,
            	'firma' => $objednavky->firma,
            	'popis' => $objednavky->popis,
            	'cinnost' => $objednavky->ref('cinnost')->cinnost,
            	'cinnostP' => $objednavky->ref('cinnost')->nazev_cinnosti,
            	'zakazka' => $objednavky->ref('zakazka')->zakazka,
            	'stredisko' => $objednavky->ref('stredisko')->stredisko,
				'castka' => $this->fmt->formatCurrency($objednavky->castka,'CZK'),
			];
			$fetchedRozpocets[] = $item;
		}
        return $fetchedRozpocets;
	}


	/**
	 * Smaže objednávky, resp. změní jejich stav v databázi na smazané
	 */
	public function smazObjednavkyDb(array $ids): void
    {
        // TODO: tady je nutne doplnit kod kontrolujici, zda ma uzivatel pravo objednavku smazat

        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 7
        ]);
	}
	


}