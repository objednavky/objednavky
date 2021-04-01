<?php

namespace App\Presenters;

use App\Model\ObjednavkyManager;
use Nette;
use Nette\Utils\DateTime;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use App\Utils;


class UpravaPresenter extends ObjednavkyBasePresenter
{
    
    private bool $formHasErrors;
    
    /** @persistent */
    public int $objId;


    protected function startup() {
        parent::startup();
    }

    public function renderDefault(int $objId): void
	{
        bdump("renderDefault(".$objId.")");
        bdump("2renderDefault(".$objId.")");
        if (null == $objId) {
            // když není nastaven id, zkus ho vytáhnout ze session
            $objId = $this->getSession('UpravaPresenter')->objId;
        } else {
            // když je nastaven id, ulož ho do session pro příště
            $this->getSession('UpravaPresenter')->objId = $objId;
        }
        bdump($objId);
        $this->objId = $objId;

        //TK: doplnit kontrolu kdyz neni vyplnene ID, neni v editovatelnem stavu nebo nam obj. nepatri => error
    }


    protected function createComponentObjednavkyMultipleForm(): Form
    {
        $parametry=[];
        
        $rok=$this->getSetup(1)->rok; 
        $cinnost = $this->database->table('cinnost')->where('vyber',1)->where('rok',$rok);
        foreach ($cinnost as $polozka) 
        {
            $dohromady = $polozka->cinnost . " ".$polozka->nazev_cinnosti;
            $parametry['cinnosti'][$polozka->id] = $dohromady;
        }
        $zakazka = $this->database->table('zakazky')->where('vyber',1);
        foreach ($zakazka as $polozka) 
        {
            $dohromady = $polozka->zakazka . " ".$polozka->popis;
            $parametry['zakazky'][$polozka->id] = $dohromady;
        }
        $stredisko = $this->database->table('stredisko')->where('vyber',1);
        foreach ($stredisko as $polozka) 
        {
            $dohromady = $polozka->stredisko;
            $parametry['strediska'][$polozka->id] = $dohromady;
        }
        $form = new Form;
        // $row = ['popis_radky' => '', 'cinnostVyber' => '', 'zakazkaVyber' => '','strediskoVyber' => '','castka' => ''];
        // $form->setDefaults($row);
        $form->onRender[] = '\App\Utils\FormStylePomocnik::makeBootstrap4Objednavka';
        $form->addGroup('Objednávka ');
        $form->addHidden('id');
        $form->addText('popis', 'Název objednávky: ')->setRequired('Napište název objednávky')->setMaxLength(100);

        $multiplier = $form->addMultiplier('polozka', function (Nette\Forms\Container $polozkaContainer, Nette\Forms\Form $form) use ($parametry) {
            $polozkaContainer->addHidden('polozkaId');
            $polozkaContainer->addHidden('smazanaForm', 'Tato položka bude smazána.');
            $polozkaContainer->addHidden('ulozenaForm');
            $polozkaContainer->addHidden('zmenenaForm', 'Tato položka bude změněna.');

            $polozkaContainer->addText('popis_radky', 'Popis položky:')->setOption('description', 'Pokud nevyplníte, použije se název objednávky')->setMaxLength(100);
            $polozkaContainer->addText('firma', 'Firma (prodejce):')->setMaxLength(100)
                ->addConditionOn($polozkaContainer['ulozenaForm'], $form::EQUAL, false)->setRequired('Napište název firmy.');
            $polozkaContainer->addSelect('cinnostVyber', 'Činnost:',$parametry['cinnosti'] )->setPrompt(' ')
                ->addConditionOn($polozkaContainer['ulozenaForm'], $form::EQUAL, false)->setRequired('Vyberte prosím činnost');
            $polozkaContainer->addSelect('zakazkaVyber', 'Zakázka:',$parametry['zakazky'] )->setPrompt(' ')
                ->addConditionOn($polozkaContainer['ulozenaForm'], $form::EQUAL, false)->setRequired('Vyberte prosím zakázku');
            $polozkaContainer->addSelect('strediskoVyber', 'Středisko:',$parametry['strediska'] )->setPrompt(' ')
                ->addConditionOn($polozkaContainer['ulozenaForm'], $form::EQUAL, false)->setRequired('Vyberte prosím středisko');
            $polozkaContainer->addInteger('castka', 'Částka v Kč:' )
                ->addConditionOn($polozkaContainer['ulozenaForm'], $form::EQUAL, false)->setRequired('Zadejte částku' )->addRule($form::RANGE,'Zadejte nejméně %d a nejvíce %d Kč' , [1, 2000000]);
            $polozkaContainer->addHidden('stav_popis');

            // skryte polozky na uchovani default hodnot
            $polozkaContainer->addHidden('popis_radky_hidden');
            $polozkaContainer->addHidden('firma_hidden');
            $polozkaContainer->addHidden('cinnostVyber_hidden');
            $polozkaContainer->addHidden('zakazkaVyber_hidden');
            $polozkaContainer->addHidden('strediskoVyber_hidden');
            $polozkaContainer->addHidden('castka_hidden');
            $polozkaContainer->addHidden('stav_hidden');

            $polozkaContainer->addButton('removeOld', 'Odebrat')
                ->setHtmlAttribute('onclick', 'smazatUlozene(this)')
                ->setHtmlAttribute('class','btn btn-danger');
            $polozkaContainer->addButton('editOld', 'Upravit')
                ->setHtmlAttribute('onclick', 'editovatUlozene(this)')
                ->setHtmlAttribute('class','btn btn-info');
            $polozkaContainer->addButton('revertOld', 'Ponechat uloženou')
                ->setHtmlAttribute('onclick', 'vratitUlozene(this)')
                ->setHtmlAttribute('class','btn btn-secondary')
                ->setHtmlAttribute('style','display: none;');
            $polozkaContainer->addButton('removeNew', 'Smazat')
                ->setHtmlAttribute('onclick', 'smazatNove(this)')
                ->setHtmlAttribute('class','btn btn-danger')
                ->setHtmlAttribute('style','display: none;');
    
        }, 1);
        
        $form->addButton('addNew','Přidat novou položku')
            ->setHtmlAttribute('onclick', 'copyLast()')
            ->setHtmlAttribute('class','btn btn-success');

        $form->addSubmit('hotovo', 'Uložit změny')->setHtmlAttribute('class','btn btn-primary');;
        $form->onSuccess[] = [$this, 'objednavkyMultipleFormSucceeded'];
        bdump($form);
        bdump($form->components['polozka']);


        //naplneni daty z DB
        $data = $this->mapObjednavkaProUpravu();
        $form->setValues($data);

        return $form;
    }
    


    /**
     * zpracuje data přijatá po odeslání formuláře s novou objednávkou
     */
    public function objednavkyMultipleFormSucceeded(Form $form, $data): void
    {
        $polozky = $this->dataProMultiInsert($form, $data);

        if ($this->formHasErrors) {
            $this->formHasErrors = false;
            return;
        }
        
        $this->database->beginTransaction(); // zahájení transakce
        try {
            //  uloz do databaze nejdriv zmenenou hlavicku objednavky ...
            $this->database->table('prehled')->where('id', $data['id'])->update(['popis' => $data->popis]);

            // ... a nasledne uloz zmenene i polozky
            foreach ($polozky as $polozka) {
                switch ($polozka['_akce']) {
                    case OBJ_DB_NOVA:
                        unset($polozka['_akce']);
                        $this->database->table('objednavky')->insert($polozka);
                        break;
                    case OBJ_DB_ZMENA:
                        unset($polozka['_akce']);
                        $this->database->table('objednavky')->where('id', $polozka['id'])->update($polozka);
                        break;
                    case OBJ_DB_SMAZANI:
                        $this->objednavkyManager->smazObjednavkyDb([$polozka['id']]);
                        break;
                    case OBJ_DB_IGNORUJ:
                        //nedelej nic
                        break;
                }
            }
            $this->database->commit();

        } catch (\Exception $e) {
            bdump($e);
            $this->database->rollback();
            $this->flashMessage('Chyba při ukládání objednávky do databáze: '.$e->getMessage(), 'error');
            $this->redirect('this');
        }
        
        $this->flashMessage('Objednávka založena.');
        $this->redirect('VsechnyObjednavky:default', ['prehledId' => $this->objId]);
    }

    public function dataProMultiInsert(Form $form, $data) : array
    {
        $rok=$this->getSetup(1)->rok; 
        //zjitim rok a verzi;
        //$verze=$this->getSetup(1)->verze;
        $verze = MujPomocnik::getSetupGlobal($this->database, 1);
        
        $this->formHasErrors = false;

        bdump($rok);

        $limityRozpoctu = [];
        $polozky = [];

        $relevantni = $this->database->table('zakazky')->where('vlastni', 1)->select('zakazka'); 
        $relevantniId = $this->database->table('zakazky')->where('vlastni', 1)->select('id');

        foreach ($data->polozka as $id => $polozka) {
            
            // u ulozenych, ale needitovanych polozek vrat puvodni hodnoty (jsou disabled = neodeslaly se jejich hodnoty, pouzijeme zalohu z hidden)
            if ($polozka->ulozenaForm && !$polozka->zmenenaForm) {
                $polozka->popis_radky = $polozka->popis_radky_hidden;
                $polozka->firma = $polozka->firma_hidden;
                $polozka->cinnostVyber = $polozka->cinnostVyber_hidden;
                $polozka->zakazkaVyber = $polozka->zakazkaVyber_hidden;
                $polozka->strediskoVyber = $polozka->strediskoVyber_hidden;
                $polozka->castka =  $polozka->castka_hidden == '' ? null : intval($polozka->castka_hidden);
            }
            bdump($polozka);

            $cinnost = $this->database->table('cinnost')->where('vyber',1)->where('rok',$rok)->where('id',$polozka->cinnostVyber)->fetch();
            $zakazka = $this->database->table('zakazky')->where('vyber',1)->where('id',$polozka->zakazkaVyber)->fetch();

            $castkaRozpoctu = $cinnost->rozpocet->castka;

            // pro kontrolu limitu musime zjistit, zda se polozka rusi, meni nebo nove pridava
            $castkaUpravena = $polozka->castka;
            if ($polozka->ulozenaForm) {
                if ($polozka->smazanaForm) {
                    // stavajici polozka bude smazana = puvodni castku odecti
                    $castkaUpravena = - $polozka->castka_hidden;
                } elseif ($polozka->zmenenaForm) {
                    if (in_array($polozka->stav_hidden, [0,1,3,4,9])) {
                        // pokud stavajici polozka bude editovana a zaroven se podle puvodniho stavu pocitala do limitu, odecti puvodni castku
                        $castkaUpravena -=  $polozka->castka_hidden;
                    } 
                } else {
                    // stavajici polozka bude ponechana = od aktualni castky odecti puvodni
                    $castkaUpravena -=  $polozka->castka_hidden;
                }
            }

            if ($zakazka->vlastni == 1) {
                $castkaVlastni = $castkaUpravena;
            } else {
                $castkaVlastni = 0;
            }

            if (!array_key_exists($cinnost->id_rozpocet, $limityRozpoctu)) {
                $objednanoV = $this->database->table('objednavky')->where('cinnost.id_rozpocet', $cinnost->id_rozpocet)->where('zakazka',$relevantniId)
                    ->where('stav',[0,1,3,4,9])->sum('castka');
                $denikV = $this->database->table('denik')->where('rozpocet', $cinnost->id_rozpocet)->where('zakazky',$relevantni)
                    ->where('petky',1)->sum('castka');
                $maxCastka = round($castkaRozpoctu - ($objednanoV + $denikV));
                
                $limityRozpoctu[$cinnost->id_rozpocet] = [
                    'nazevRozpoctu' => $cinnost->rozpocet->rozpocet,
                    'castkaRozpoctu' => $castkaRozpoctu, 
                    'objednano' => $objednanoV,
                    'denik' => $denikV,
                    'limit' => $maxCastka,
                    'pozadovanoVlastni' => $castkaVlastni,
                    'pozadovanoCelkem' => $castkaUpravena,
                    'overeni' => $cinnost->rozpocet->overeni,
                    'kdoma' => $cinnost->rozpocet->hospodar,
                    'kdoma2'=> $cinnost->rozpocet->overovatel,
                ];
            } else {
                $limityRozpoctu[$cinnost->id_rozpocet]['pozadovanoVlastni'] += $castkaVlastni;
                $limityRozpoctu[$cinnost->id_rozpocet]['pozadovanoCelkem'] += $castkaUpravena;
            }
        }
        bdump($limityRozpoctu);

        foreach ($data->polozka as $id => $polozka) {
            $cinnost = $this->database->table('cinnost')->where('vyber',1)->where('rok',$rok)->where('id',$polozka->cinnostVyber)->fetch();
            $stredisko = $this->database->table('stredisko')->where('vyber',1)->where('id',$polozka->strediskoVyber)->fetch();
            $zakazka = $this->database->table('zakazky')->where('vyber',1)->where('id',$polozka->zakazkaVyber)->fetch();

            $castkaRozpoctu = $cinnost->rozpocet->castka;            

            $polozka->popis_radky =  $polozka->popis_radky == null ? $data->popis : $polozka->popis_radky;

            //  kontrola, zda suma vsech polozek do daneho rozpoctu nepresahuje overovani
            if ($limityRozpoctu[$cinnost->id_rozpocet]['overeni'] <= $limityRozpoctu[$cinnost->id_rozpocet]['pozadovanoCelkem']) {
                $nutnoOverit = 1;
            } else {
                $nutnoOverit = 0;
            }

            // default stav = 0 (ve schvalovani)
            $stav = 0;        
            $schvalil = null;     

            // pokud je zadavatel schvalovatelem, je objednavka rovnou schvalena
            if  ($limityRozpoctu[$cinnost->id_rozpocet]['kdoma'] == $this->prihlasenyId()) {
                $schvalil = new DateTime();
                if ($nutnoOverit==1) {
                    $stav = 1;
                }  else {
                    $stav = 3;
                }
            }

            if ($polozka->ulozenaForm) {
                if ($polozka->zmenenaForm) {
                    $akce = OBJ_DB_ZMENA;
                } elseif ($polozka->smazanaForm) {
                    $akce = OBJ_DB_SMAZANI;
                } else {
                    $akce = OBJ_DB_IGNORUJ;
                }
            } else {
                $akce = OBJ_DB_NOVA;
            }
            
            $polozky[] = [
                '_akce' => $akce,
                'id' => ($polozka->polozkaId === '' ? null : intval($polozka->polozkaId)),
                'id_prehled' => $data->id,
                'radka' => $id + 1,                                               
                'castka' => $polozka->castka,
                'firma' => $polozka->firma,
                'popis' => $polozka->popis_radky,
                'cinnost' =>  $cinnost->id,
                'stredisko' => $stredisko->id,
                'zakazka' => $zakazka->id,
                'kdo' => $limityRozpoctu[$cinnost->id_rozpocet]['kdoma'],
                'kdo2' => $limityRozpoctu[$cinnost->id_rozpocet]['kdoma2'],
                'zakladatel' => $this->prihlasenyId(),
                'nutno_overit' => $nutnoOverit,
                'presne' => true,
                'stav' => $stav,
                'schvalil' => $schvalil,
            ];
        }
        bdump($polozky);

        // hromadna kontrola prekroceni rozpoctu
        foreach ($limityRozpoctu as $limitRozpoctu) {
            bdump($limitRozpoctu);
            if  (($limitRozpoctu['pozadovanoVlastni'] > 0) && ($limitRozpoctu['pozadovanoVlastni'] > $limitRozpoctu['limit'])) {
                $this->formHasErrors = true;
                $form['popis']->addError('Objednávku pro rozpočet '.$limitRozpoctu['nazevRozpoctu'].' nelze zadat, byl by překročen rozpočet. Zbývá částka ' . $limitRozpoctu['limit'] .' Kč.' );
            }
        }
        return $polozky;
    } 


    /* *********************************************************************************************************** */
    private function mapObjednavkaProUpravu() : stdClass {

        $objId = $this->objId;
        $data = new stdClass();

        $prehled = $this->database->table('prehled')->get($objId);


        //TODO doplnit kontrolu na existenci objednavky

        //TODO doplnit kontrolu na opravneni k editaci objednavky


        $objednavky = $this->database->table('objednavky')->where('id_prehled', $objId);

        $data->id = $prehled->id;
        $data->popis = $prehled->popis;
        $data->zakladatel = $prehled->zakladatel;

        foreach ($objednavky as $objednavka) {
            $item = new stdClass;
            $item->polozkaId = $objednavka->id;
            $item->id_prehled = $objednavka->id_prehled;

            $item->popis_radky = $objednavka->popis;
            $item->schvalovatel = $objednavka->kdo;
            $item->schvalil = $objednavka->schvalil;
            $item->overovatel = $objednavka->kdo2;
            $item->overil = $objednavka->overil;             
            $item->stav = $objednavka->stav;
            $item->firma = $objednavka->firma;
            $item->cinnostVyber = $objednavka->cinnost;
            $item->zakazkaVyber = $objednavka->zakazka;
            $item->strediskoVyber = $objednavka->stredisko;
            $item->castka =  $objednavka->castka;
            $item->stav_popis = $objednavka->ref('stav')->popis;

            $item->popis_radky_hidden = $objednavka->popis;
            $item->firma_hidden = $objednavka->firma;
            $item->cinnostVyber_hidden = $objednavka->cinnost;
            $item->zakazkaVyber_hidden = $objednavka->zakazka;
            $item->strediskoVyber_hidden = $objednavka->stredisko;
            $item->castka_hidden =  $objednavka->castka;
            $item->stav_hidden = $objednavka->stav;

            $item->smazanaForm = false;
            $item->zmenenaForm = false;
            $item->ulozenaForm = true;
 
            $data->polozka[] = $item;
          }       
        return $data;
    }

    
}