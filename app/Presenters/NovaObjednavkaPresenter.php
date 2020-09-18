<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use App\Utils;


class NovaObjednavkaPresenter extends ObjednavkyBasePresenter
{
    
    private $mojeParametryNaPrasaka = [];

    protected function startup()
    {
        parent::startup();
    }


    public function renderShow(): void
	{
        $this->template->cisloRadku = 1;
    }

    public function renderDruhy(): void
	{
     
    }

    protected function createComponentObjednavkyForm1(): Form
    {
        $cinnost = $this->database->table('cinnost')->where('vyber',1);
        foreach ($cinnost as $polozka) 
        {
            $dohromady = $polozka->cinnost . " ".$polozka->nazev_cinnosti;
            $fetchedNovac[$polozka->id] = $dohromady;
        }
        $zakazka = $this->database->table('zakazky')->where('vyber',1);
        foreach ($zakazka as $polozka) 
        {
            $dohromady = $polozka->zakazka . " ".$polozka->popis;
            $fetchedNovaz[$polozka->id] = $dohromady;
        }
        $stredisko = $this->database->table('stredisko')->where('vyber',1);
        foreach ($stredisko as $polozka) 
        {
            $dohromady = $polozka->stredisko;
            $fetchedNovas[$polozka->id] = $dohromady;
        }
        $polozkaC = "Položka č. 1";
        $form = new Form;
        // $row = ['popis_radky' => '', 'cinnostVyber' => '', 'zakazkaVyber' => '','strediskoVyber' => '','castka' => ''];
        // $form->setDefaults($row);
        $form->onRender[] = '\App\Utils\FormStylePomocnik::makeBootstrap4';
        $form->addGroup('Objednávka ');
        $form->addText('popis', 'Název celé objednávky: ')->setRequired('Napište název objednávky');
        $form->addGroup($polozkaC);
        $form->addText('popis_radky', 'Popis:')->setOption('description', 'Pokud nevyplníte, použije se název objednávky');
        $form->addText('firma', 'Firma (prodejce):')->setRequired('Napište název firmy.');
        $form->addSelect('cinnostVyber', 'Činnost:',$fetchedNovac )->setRequired('Vyberte prosím činnost')->setPrompt(' ');
        $form->addSelect('zakazkaVyber', 'Zakázka:',$fetchedNovaz )->setRequired('Vyberte prosím zakázku')->setPrompt(' ');
        $form->addSelect('strediskoVyber', 'Středisko:',$fetchedNovas )->setRequired('Vyberte prosím zakázku')->setPrompt(' ');
        $castkaObj = "  ";
        $form->addGroup($castkaObj);
        $form->addInteger('castka', 'Částka v Kč:' )
            ->setRequired('Zadejte částku' );
        $form->addRadioList('presne', 'Je částka přesná?   ',
                ['  Ano, na faktuře bude přesně tato částka', '  Ne, částka může být v rozsahu +- 10 procent'])->setDefaultValue(0);
        $form->addSubmit('pridat', 'Přidat další položku');
        $form->addSubmit('hotovo', 'Hotovo');
        $form->onSuccess[] = [$this, 'objednavkyForm1Succeeded'];
        return $form;
    }

    protected function createComponentObjednavkyForm2(): Form
    {
        $uz = $this->prihlasenyId();
        // $posledni = $this->database->table('prehled')->where('zakladatel',$uz)->max('id');
        $posledniO = $this->database->table('objednavky')->where('zakladatel',$uz)->max('id_prehled');
        $posledniR = $this->database->table('objednavky')->where('zakladatel',$uz)->where('id_prehled',$posledniO)->max('radka');
        $posledniF = ($this->database->table('objednavky')->where('zakladatel',$uz)->where('id_prehled',$posledniO)->fetch())->firma;
        $posledniP = ($this->database->table('prehled')->where('id',$posledniO)->fetch())->popis;
        $posledniS = $this->database->table('objednavky')->where('id_prehled',$posledniO)->sum('castka');
        $posledniR= ++$posledniR;
        $cinnost = $this->database->table('cinnost')->where('vyber',1);
        foreach ($cinnost as $polozka) 
        {
            $dohromady = $polozka->cinnost . " ".$polozka->nazev_cinnosti;
            $fetchedNovac[$polozka->id] = $dohromady;
        }
        $zakazka = $this->database->table('zakazky')->where('vyber',1);
        foreach ($zakazka as $polozka) 
        {
            $dohromady = $polozka->zakazka . " ".$polozka->popis;
            $fetchedNovaz[$polozka->id] = $dohromady;
        }
        $stredisko = $this->database->table('stredisko')->where('vyber',1);
        foreach ($stredisko as $polozka) 
        {
            $dohromady = $polozka->stredisko;
            $fetchedNovas[$polozka->id] = $dohromady;
        }
        $polozkaC = "Položka č. ". $posledniR. " -  předchozí položky mají zadanou celkovou částku ". $posledniS."  Kč";
        $form = new Form;
        $row = ['popis' => $posledniP, 'firma' => $posledniF];
        $form->setDefaults($row);
        $form->onRender[] = '\App\Utils\FormStylePomocnik::makeBootstrap4';
        $form->addGroup('Objednávka ');
        $form->addText('popis', 'Název celé objednávky: ')->setDisabled()->setDefaultValue($posledniP);
        $form->addInteger('id_prehled', 'Číslo objednávky:' )->setDisabled()->setDefaultValue($posledniO);
        $form->addInteger('radka', 'Číslo řádky:' )->setDisabled()->setDefaultValue($posledniR);
        $form->addGroup($polozkaC);
        $form->addText('firma', 'Firma (prodejce):')->setDefaultValue($posledniF);
        $form->addText('popis_radky', 'Popis:')->setOption('description', 'Pokud nevyplníte, použije se název objednávky');
        $form->addSelect('cinnostVyber', 'Činnost:',$fetchedNovac )->setRequired('Vyberte prosím činnost')->setPrompt(' ');
        $form->addSelect('zakazkaVyber', 'Zakázka:',$fetchedNovaz )->setRequired('Vyberte prosím zakázku')->setPrompt(' ');
        $form->addSelect('strediskoVyber', 'Středisko:',$fetchedNovas )->setRequired('Vyberte prosím zakázku')->setPrompt(' ');
        $castkaObj = "  ";
        $form->addGroup($castkaObj);
        $form->addInteger('castka', 'Částka v Kč:' )
            ->setRequired('Zadejte částku' );
        $form->addRadioList('presne', 'Je částka přesná?   ',
            ['  Ano, na faktuře bude přesně tato částka', '  Ne, částka může být v rozsahu +- 10 procent'])->setDefaultValue(0);
        $form->addSubmit('pridat', 'Přidat další položku');
        $form->addSubmit('hotovo', 'Hotovo');
        $form->onSuccess[] = [$this, 'objednavkyForm2Succeeded2'];
        return $form;
    }

    public function dataProInsert(Form $form, $data)
    {
        $rok=$this->getSetup(1)->rok;      //zjitim rok a verzi;
        //$verze=$this->getSetup(1)->verze;
        $verze = MujPomocnik::getSetupGlobal($this->database, 1);
        $cinnostdatab = $this->database->table('cinnost')->where('vyber',1)->select('id');
        $strediskotdatab = $this->database->table('stredisko')->where('vyber',1)->select('id');
        $zakazkadatab = $this->database->table('zakazky')->where('vyber',1)->select('id');
        $cinnost = $cinnostdatab[$data->cinnostVyber];
        $stredisko = $strediskotdatab[$data->strediskoVyber];
        $zakazka = $zakazkadatab[$data->zakazkaVyber];
        $zadanaCastka = $data->castka;
        bdump($cinnost);
        $pomoc = $this->database->table('cinnost')->where('id',$cinnost)->where('rok',$rok)->select('id_rozpocet');
        $radkaRozpoctu = $this->database->table('rozpocet')->where('id',$pomoc)->fetch();
        $kdoma = $radkaRozpoctu->hospodar;
        $overeni = $radkaRozpoctu->overeni;
        $kdoma2= $radkaRozpoctu->overovatel;
        $cinnost_d = $this->database->table('cinnost')->where('id',$cinnost)->fetch();
        $castkaRozpoctu = $radkaRozpoctu->castka;
        $relevantni = $this->database->table('zakazky')->where('vlastni', 1)->select('zakazka'); 
        $relevantniId = $this->database->table('zakazky')->where('vlastni', 1)->select('id');
        $objednanoV = $this->database->table('objednavky')->where('cinnost', $cinnost)->where('zakazka',$relevantniId)
            ->where('stav',[0,1,3,4,9])->sum('castka');
        $denikV = $this->database->table('denik')->where('cinnost_d', $cinnost_d->cinnost)->where('zakazky',$relevantni)
            ->where('petky',1)->sum('castka');
        $maxCastka = round($castkaRozpoctu - ($objednanoV + $denikV));
        bdump($zakazka->id);
        $jeVlastnii = $this->database->table('zakazky')->where('id', $zakazka->id)->fetch();
        $jeVlastni = $jeVlastnii->vlastni;
        $this->mojeParametryNaPrasaka['formHasError'] = 0;
        if ($jeVlastni == 1)
        {
            if  ( $data->castka > $maxCastka) {
                $this->mojeParametryNaPrasaka['formHasError'] = 1;
                $form['castka']->addError('Objednávku nelze zadat, byl překročen rozpočet. Zbývá částka ' . $maxCastka .' Kč.' );
                return;
            }
        }
        $novyradek = $this->database->table('prehled')->insert([
            'popis' => $data->popis,
        ]);
        $posledni = $this->database->table('prehled')->max('id');
        $data->popis_radky =  $data->popis_radky == NULL ? $data->popis : $data->popis_radky;
        // $kdoma = $this->database->table('prehled')->max('id');
        $celkem = $objednanoV + $denikV+ $data->castka;
        if ($overeni <= $zadanaCastka)             // např. 10 tis < 450Kč
        {
            $nutnoOverit = 1;
            //  částka převyšuje povolenou velikost bez ověření
        } else {
            $nutnoOverit = 0;
            // částka je menší, není nutné ověřovat
        }
        bdump(($overeni <= $zadanaCastka ));
        bdump($nutnoOverit);
        $stav = 0;             
        if  ($kdoma == $this->prihlasenyId()) {
            if ($nutnoOverit==1) {$stav = 1;}  else {
                $stav = 3;
            }
        } 
        bdump($stav);
        $this->database->table('objednavky')->insert([
            'id_prehled' => $posledni,
            'radka' => 1,                                                // tady bude cislo radky
            'castka' => $data->castka,
            'firma' => $data->firma,
            'popis' => $data->popis_radky,
            'cinnost' =>  $cinnost->id,
            'stredisko' => $stredisko->id,
            'zakazka' => $zakazka->id,
            'kdo' => $kdoma,
            'kdo2' => $kdoma2,
            'zakladatel' => $this->prihlasenyId()->id,
            'nutno_overit' => $nutnoOverit,
            'presne' => $data->presne,
            'stav' => $stav
        ]);
        $this->mojeParametryNaPrasaka['posledni'] = $posledni;
        return $posledni;
    } 

    public function objednavkyForm1Succeeded(Form $form, $data): void
    {
        $posledni = $this->dataProInsert($form, $data);
        if ($this->mojeParametryNaPrasaka['formHasError'] == 1) {
            $this->mojeParametryNaPrasaka['formHasError'] = 0;
            return;
        }
        if ($form->isSubmitted()->getName() == 'hotovo') {
            $this->flashMessage('Objednávka zapsána.');
            $this->redirect('Homepage:');
        } else { 
            $this->flashMessage('Další položka.');
            $this->redirect('NovaObjednavka:druhy');
        }
        $url = $this->link('NovaObjednavka:druhy');//, $this->mojeParametryNaPrasaka['posledni']);
        //$url = $this->link('NovaObjednavka:druhyKrok', ['id' => $this->mojeParametryNaPrasaka['posledni'], 'firma' => 'mojefirma']);
        bdump($url);
        $this->redirect($url);
    }

    public function dataProInsert2(Form $form, $data)
    {
        $rok=$this->getSetup(1)->rok;      //zjitim rok a verzi;
        //$verze=$this->getSetup(1)->verze;
        $verze = MujPomocnik::getSetupGlobal($this->database, 1);

        $cinnostdatab = $this->database->table('cinnost')->where('vyber',1)->select('id');
        $strediskotdatab = $this->database->table('stredisko')->where('vyber',1)->select('id');
        $zakazkadatab = $this->database->table('zakazky')->where('vyber',1)->select('id');

        $cinnost = $cinnostdatab[$data->cinnostVyber];
        $stredisko = $strediskotdatab[$data->strediskoVyber];
        $zakazka = $zakazkadatab[$data->zakazkaVyber];

        $zadanaCastka = $data->castka;
        bdump($cinnost);
        
        $pomoc = $this->database->table('cinnost')->where('id',$cinnost)->where('rok',$rok)->select('id_rozpocet');
        $radkaRozpoctu = $this->database->table('rozpocet')->where('id',$pomoc)->fetch();
        $kdoma = $radkaRozpoctu->hospodar;
        $overeni = $radkaRozpoctu->overeni;
        $kdoma2= $radkaRozpoctu->overovatel;
        $cinnost_d = $this->database->table('cinnost')->where('id',$cinnost)->fetch();
        $castkaRozpoctu = $radkaRozpoctu->castka;
        $relevantni = $this->database->table('zakazky')->where('vlastni', 1)->select('zakazka'); 
        $relevantniId = $this->database->table('zakazky')->where('vlastni', 1)->select('id');
        $objednanoV = $this->database->table('objednavky')->where('cinnost', $cinnost)
            ->where('zakazka',$relevantniId)->where('stav',[0,1,3,4,9])->sum('castka');
        $denikV = $this->database->table('denik')->where('cinnost_d', $cinnost_d->cinnost)->where('zakazky',$relevantni)
            ->where('petky',1)->sum('castka');
        $maxCastka = round($castkaRozpoctu - ($objednanoV + $denikV));
        $jeVlastnii = $this->database->table('zakazky')->where('id', $zakazka->id)->fetch();
        $jeVlastni = $jeVlastnii->vlastni;
        $this->mojeParametryNaPrasaka['formHasError'] = 0;
        if ($jeVlastni == 1) {
            if  ( $data->castka > $maxCastka) {
                $this->mojeParametryNaPrasaka['formHasError'] = 1;
                $form['castka']->addError('Objednávku nelze zadat, byl překročen rozpočet. Zbývá částka ' . $maxCastka .' Kč.' );
                return;
            }
        }

        // $novyradek = $this->database->table('prehled')->insert([
        //     'popis' => $data->popis,
        // ]);
        // $posledni = $this->database->table('prehled')->max('id');
        // $data->popis_radky =  $data->popis_radky == NULL ? $data->popis : $data->popis_radky;
        // // $kdoma = $this->database->table('prehled')->max('id');
        // bdump($posledni) ;

        if (($overeni <= $zadanaCastka )) 
        // && ($castkaRozpoctu < ($objednanoV + $denikV+ $data->castka))) 
        {
            $nutnoOverit = 1;
            // prekrocili jsme castku již v deníku a objednaných  nebo částka převyšuje povolenou velikost
        } else {
            $nutnoOverit = 0;
            // částka je menší, není nutné ověřovat
        }
        $uz = $this->prihlasenyId();
        $posledniO = $this->database->table('objednavky')->where('zakladatel',$uz)->max('id_prehled');
        $posledniR = $this->database->table('objednavky')->where('zakladatel',$uz)->where('id_prehled',$posledniO)->max('radka');
        $posledniF = ($this->database->table('objednavky')->where('zakladatel',$uz)->where('id_prehled',$posledniO)->fetch())->firma;
        $posledniR= ++$posledniR;  
        $stav = 0;             
        if  ($kdoma== $this->prihlasenyId()->id) {
            if ($nutnoOverit==1) {$stav = 1;}  else {$stav = 3;}
        } 
        
        $this->database->table('objednavky')->insert([
            'id_prehled' => $posledniO,
            'radka' => $posledniR,                                                // tady bude cislo radky
            'castka' => $data->castka,
            'firma' => $posledniF,
            'popis' => $data->popis_radky,
            'cinnost' =>  $cinnost->id,
            'stredisko' => $stredisko->id,
            'zakazka' => $zakazka->id,
            'kdo' => $kdoma,
            'kdo2' => $kdoma2,
            'zakladatel' => $this->prihlasenyId()->id,
            'nutno_overit' => $nutnoOverit,
            'presne' => $data->presne,
            'stav' => $stav
        ]);
        return ;
    }     

    public function objednavkyForm2Succeeded2(Form $form, $data): void
    {
        $posledni = $this->dataProInsert2($form, $data);
        if ($this->mojeParametryNaPrasaka['formHasError'] == 1) {
            $this->mojeParametryNaPrasaka['formHasError'] = 0;
            return;
        }
        if ($form->isSubmitted()->getName() == 'hotovo') {
            $this->flashMessage('Objednávka zapsána.');
            $this->redirect('Homepage:');
        } else { 
            $this->flashMessage('Další položka.');
            $this->redirect('NovaObjednavka:druhy');
        }

        $url = $this->link('NovaObjednavka:druhy');//, $this->mojeParametryNaPrasaka['posledni']);
        //$url = $this->link('NovaObjednavka:druhyKrok', ['id' => $this->mojeParametryNaPrasaka['posledni'], 'firma' => 'mojefirma']);
        bdump($url);
        $this->redirect($url);
    }
    
}