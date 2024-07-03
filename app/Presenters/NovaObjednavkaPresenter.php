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
        $form->onRender[] = '\App\Utils\FormStylePomocnik::makeBootstrap4Objednavka';
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
                ->setRequired('Zadejte částku' )->addRule($form::RANGE,'Zadejte nejméně %d a nejvíce %d Kč' , [1, 99000000]);
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
        $novyRadek = $this->database->table('prehled')->insert([
            'popis' => $data->popis,
            'zakladatel' => $this->prihlasenyId(),
            'zalozil' => new DateTime(),
        ]);
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
        //zjitim rok a verzi;
        $rok=$this->getSetup()->rok; 
        $verze = $this->getSetup()->verze;
        
        $this->formHasErrors = false;

        bdump($rok);

        $limityRozpoctu = [];
        $polozky = [];

        $relevantniV = $this->database->table('zakazky')->where('vlastni', 1)->select('zakazka'); 
        $relevantniVId = $this->database->table('zakazky')->where('vlastni', 1)->select('id');
        $relevantniS = $this->database->table('zakazky')->where('sablony', 1)->select('zakazka'); 
        $relevantniSId = $this->database->table('zakazky')->where('sablony', 1)->select('id');

        // prvni iterace - soucet castek pro kontrolu precerpani a pro overovani
        foreach ($data->polozka as $id => $polozka) {
            $cinnost = $this->database->table('cinnost')->where('vyber',1)->where('rok',$rok)->where('id',$polozka->cinnostVyber)->fetch();
            $zakazka = $this->database->table('zakazky')->where('vyber',1)->where('id',$polozka->zakazkaVyber)->fetch();

            if ($zakazka->vlastni == 1) {
                $limityRozpoctu = $this->objednavkyManager->pridejLimitRozpoctu($limityRozpoctu, $polozka->cinnostVyber, $polozka->zakazkaVyber, $polozka->castka, 0);
            } elseif ($zakazka->sablony == 1) {
                $limityRozpoctu = $this->objednavkyManager->pridejLimitRozpoctu($limityRozpoctu, $polozka->cinnostVyber, $polozka->zakazkaVyber, 0, $polozka->castka);
            } else {
                $limityRozpoctu = $this->objednavkyManager->pridejLimitRozpoctu($limityRozpoctu, $polozka->cinnostVyber, $polozka->zakazkaVyber, 0, 0, $polozka->castka);
            }
        }
        bdump($limityRozpoctu);
                      
        foreach ($data->polozka as $id => $polozka) {
            $cinnost = $this->database->table('cinnost')->where('vyber',1)->where('rok',$rok)->where('id',$polozka->cinnostVyber)->fetch();
            $stredisko = $this->database->table('stredisko')->where('vyber',1)->where('id',$polozka->strediskoVyber)->fetch();
            $zakazka = $this->database->table('zakazky')->where('vyber',1)->where('id',$polozka->zakazkaVyber)->fetch();

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
                'kdo' => $limityRozpoctu[$cinnost->id_rozpocet]['kdoma'],
                'kdo2' => $limityRozpoctu[$cinnost->id_rozpocet]['kdoma2'],
                'zakladatel' => $this->prihlasenyId(),
                'nutno_overit' => $nutnoOverit,
                'presne' => true,
                'stav' => $stav,
                'schvalil' => $schvalil,
            ];
        }

        // hromadna kontrola prekroceni rozpoctu
        foreach ($limityRozpoctu as $limitRozpoctu) {
            if  ( $limitRozpoctu['pozadovanoVlastni'] > $limitRozpoctu['limitV']) {
                $this->formHasErrors = true;
                $form['popis']->addError('Objednávku pro rozpočet '.$limitRozpoctu['nazevRozpoctu'].' nelze zadat, byl by překročen VLASTNÍ rozpočet. Zbývá částka ' . $limitRozpoctu['limitV'] .' Kč.' );
            }
            if  ( $limitRozpoctu['pozadovanoSablony'] > $limitRozpoctu['limitS']) {
                $this->formHasErrors = true;
                $form['popis']->addError('Objednávku pro rozpočet '.$limitRozpoctu['nazevRozpoctu'].' nelze zadat, byl by překročen rozpočet ŠABLON. Zbývá částka ' . $limitRozpoctu['limitS'] .' Kč.' );
            }

        }

        return $polozky;
    } 

    
}