<?php

namespace App\Presenters;

use Nette;
use Nette\Utils\DateTime;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use App\Utils;


class NovaObjednavkaPresenter extends ObjednavkyBasePresenter
{
    
    private bool $formHasErrors;

    protected function startup() {
        parent::startup();
    }


    public function renderDefault(): void {
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
        $form->onRender[] = '\App\Utils\FormStylePomocnik::makeBootstrap4';
        $form->addGroup('Objednávka ');
        $form->addText('popis', 'Název objednávky: ')->setRequired('Napište název objednávky')->setMaxLength(100);

        $multiplier = $form->addMultiplier('polozka', function (Nette\Forms\Container $polozkaContainer, Nette\Forms\Form $form) use ($parametry) {
            $polozkaContainer->addText('popis_radky', 'Popis položky:')->setOption('description', 'Pokud nevyplníte, použije se název objednávky')->setMaxLength(100);
            $polozkaContainer->addText('firma', 'Firma (prodejce):')->setRequired('Napište název firmy.')->setMaxLength(100);
            $polozkaContainer->addSelect('cinnostVyber', 'Činnost:',$parametry['cinnosti'] )->setRequired('Vyberte prosím činnost')->setPrompt(' ');
            $polozkaContainer->addSelect('zakazkaVyber', 'Zakázka:',$parametry['zakazky'] )->setRequired('Vyberte prosím zakázku')->setPrompt(' ');
            $polozkaContainer->addSelect('strediskoVyber', 'Středisko:',$parametry['strediska'] )->setRequired('Vyberte prosím středisko')->setPrompt(' ');
            $castkaObj = "  ";
            $form->addGroup($castkaObj);
            $polozkaContainer->addInteger('castka', 'Částka v Kč:' )
                ->setRequired('Zadejte částku' )->addRule($form::RANGE,'Zadejte nejméně %d a nejvíce %d Kč' , [1, 2000000]);
    /*
            $form->addRadioList('presne', 'Je částka přesná?   ',
                    ['  Ano, na faktuře bude přesně tato částka', '  Ne, částka může být v rozsahu +- 10 procent'])->setDefaultValue(0);
    */
            }, 1);
        $multiplier->addCreateButton('Přidat položku')->addClass('btn btn-primary');
        $multiplier->addRemoveButton('Odebrat položku')->addClass('btn btn-danger');

        $form->addSubmit('hotovo', 'Hotovo')->setHtmlAttribute('class','btn btn-primary');;
        $form->onSuccess[] = [$this, 'objednavkyMultipleFormSucceeded'];
        bdump($form);
        bdump($form->components['polozka']);
        return $form;
    }
    
    /**
     * zpracuje data přijatá po odeslání formuláře s novou objednávkou
     */
    public function objednavkyMultipleFormSucceeded(Form $form, $data): void
    {
        bdump($data);
        $polozky = $this->dataProMultiInsert($form, $data);
        if ($this->formHasErrors) {
            $this->formHasErrors = false;
            return;
        }
        
        $this->database->beginTransaction(); // zahájení transakce

        // vse OK, uloz do databaze nejdriv hlavicku objednavky ...
        $novyRadek = $this->database->table('prehled')->insert(['popis' => $data->popis]);
        // ... pak dopln id hlavicky do polozek objednavky ...
        bdump($novyRadek);
        bdump($novyRadek->id);
        foreach ($polozky as $key => $polozka) {
            $polozky[$key]['id_prehled'] = $novyRadek->id;
        }
        // ... a nasledne uloz i polozky
        bdump($polozky);
        $this->database->table('objednavky')->insert($polozky);

        $this->database->commit();

        $this->flashMessage('Objednávka založena.');
        $this->redirect('MojeObjednavky:prehled');
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

        foreach ($data->polozka as $id => $polozka) {
            $cinnost = $this->database->table('cinnost')->where('vyber',1)->where('rok',$rok)->where('id',$polozka->cinnostVyber)->fetch();
            $stredisko = $this->database->table('stredisko')->where('vyber',1)->where('id',$polozka->strediskoVyber)->fetch();
            $zakazka = $this->database->table('zakazky')->where('vyber',1)->where('id',$polozka->zakazkaVyber)->fetch();

    /*        
            $radkaRozpoctu = $this->database->table('rozpocet')->where('id',$cinnost->id_rozpocet)->fetch();
            $kdoma = $radkaRozpoctu->hospodar;
            $overeni = $radkaRozpoctu->overeni;
            $kdoma2= $radkaRozpoctu->overovatel;
    */
            $kdoma = $cinnost->rozpocet->hospodar;
            $overeni = $cinnost->rozpocet->overeni;
            $kdoma2= $cinnost->rozpocet->overovatel;
            $castkaRozpoctu = $cinnost->rozpocet->castka;

            if ($zakazka->vlastni == 1) {
                if (!array_key_exists($cinnost->id_rozpocet, $limityRozpoctu)) {
                    $relevantni = $this->database->table('zakazky')->where('vlastni', 1)->select('zakazka'); 
                    $relevantniId = $this->database->table('zakazky')->where('vlastni', 1)->select('id');

                    $objednanoV = $this->database->table('objednavky')->where('cinnost', $cinnost)->where('zakazka',$relevantniId)
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
                        'pozadovano' => $polozka->castka
                    ];
                } else {
                    $limityRozpoctu[$cinnost->id_rozpocet]['pozadovano'] += $polozka->castka;
                }
            }

            bdump($limityRozpoctu);
                      
            
            $posledni = $this->database->table('prehled')->max('id');
            $polozka->popis_radky =  $polozka->popis_radky == NULL ? $data->popis : $polozka->popis_radky;

            if ($overeni <= $polozka->castka)             // např. 10 tis < 450Kč
            {
                $nutnoOverit = 1;
                //  částka převyšuje povolenou velikost bez ověření
            } else {
                $nutnoOverit = 0;
                // částka je menší, není nutné ověřovat
            }
            bdump($nutnoOverit);

            $stav = 0;        
            $schvalil = null;     
            if  ($kdoma == $this->prihlasenyId()) {
                $schvalil = new DateTime();
                if ($nutnoOverit==1) {
                    $stav = 1;
                }  else {
                    $stav = 3;
                }
            }

            // TK: TADY POKRAČOVAT

            $polozky[] = [
                'id_prehled' => null,
                'radka' => $id + 1,                                               
                'castka' => $polozka->castka,
                'firma' => $polozka->firma,
                'popis' => $polozka->popis_radky,
                'cinnost' =>  $cinnost->id,
                'stredisko' => $stredisko->id,
                'zakazka' => $zakazka->id,
                'kdo' => $kdoma,
                'kdo2' => $kdoma2,
                'zakladatel' => $this->prihlasenyId(),
                'nutno_overit' => $nutnoOverit,
                'presne' => true,
                'stav' => $stav,
                'schvalil' => $schvalil,
            ];
        }

        // hromadna kontrola prekroceni rozpoctu
        foreach ($limityRozpoctu as $limitRozpoctu) {
            if  ( $limitRozpoctu['pozadovano'] > $limitRozpoctu['limit']) {
                $this->formHasErrors = true;
                $form['popis']->addError('Objednávku pro rozpočet '.$limitRozpoctu['nazevRozpoctu'].' nelze zadat, byl by překročen rozpočet. Zbývá částka ' . $limitRozpoctu['limit'] .' Kč.' );
            }
        }

        return $polozky;
    } 

    
}