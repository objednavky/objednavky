<?php
//Úvodní uživatelská obrazovka, zobrazí rozpočty přihlášených skupin

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Utils;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;

class HomepagePresenter extends ObjednavkyBasePresenter
{
    private $grids = [];

    protected function startup()
    {
        parent::startup();
        $uz = $this->prihlasenyId();

        $pocetHospodar = $this->database->table('rozpocet')->where('hospodar =? OR hospodar2 = ?',$uz,$uz)->count('*');
        $pocetOverovatel = $this->database->table('rozpocet')->where('overovatel',$uz)->count('*');               

        //  if ($pocetOverovatel<1  && $pocetHospodar>0 ) { $this->redirect('Homepage:dve');}  
        //  if (($pocetOverovatel<1 ) && ($pocetHospodar=0 )) { $this->redirect('Homepage:tri');}  
    }
   
    public function renderDefault(): void
    {
        $uz = $this->prihlasenyId();

        $this->template->prihlasen = ($this->database->table('uzivatel')->where('id',$uz)->fetch())->jmeno;
        $this->template->rozpocty = $this->database->table('rozpocet');
        
        $source = $this->mapRozpocet(1);
        $this->template->mySum = $this->sumColumn($source, 'mySumV') + $this->sumColumn($source, 'mySumS');
        $this->template->castkaSablony = $this->sumColumn($source, 'castkaRozpocet') + $this->sumColumn($source, 'castkaSablony');
        $this->template->objed_ja_sch = $this->database->table('objednavky')->where('kdo', $uz)
            ->where('stav ', 0)->count('id');    //    počet objednávek čekající na mé schválení
        $this->template->objed_ja_ov = $this->database->table('objednavky')->where('kdo2', $uz)
            ->where('stav ', 1)->count('id');    //    počet objednávek čekající na mé ověření
        $this->template->objed_jiny_sch = $this->database->table('objednavky')->where('zakladatel', $uz)->where('stav ?', [0,1,3,4,9])
        ->count('id');    //    počet objednávek, které jsem zadal a ještě nejsou schválené
        $this->template->objed_zamitnute = $this->database->table('objednavky')->where('zakladatel', $uz)->where('stav = ? OR stav = ?', 2,5)
           ->count('id');    //    počet objednávek, které jsem zadal a byly zamítnuté
        $this->template->percent = $this->template->mySum == 0 ? 0: round(($this->template->mySum /  $this->template->castkaSablony) * 100, 0);
        $this->template->zbyva = $this->template->castkaSablony - $this->template->mySum;
    }

    public function renderDve(): void
    {
        $uz = $this->prihlasenyId();
        $this->template->prihlasen = ($this->database->table('uzivatel')->where('id',$uz)->fetch())->jmeno;
     }


    public function renderTri(): void
    {
        $uz = $this->prihlasenyId();
        $this->template->prihlasen = ($this->database->table('uzivatel')->where('id',$uz)->fetch())->jmeno;
    }

    private function mapRozpocet($argument)
    {
        $rok=$this->getSetup(1)->rok;      //zjitim rok a verzi;
        $verze=$this->getSetup(1)->verze;
        $uz = $this->prihlasenyId();   // přihlášený uživatel
        $skupina = $this->database->table('skupiny')->where('uzivatel',$uz)->select('rozpocet');   //vyberu nastavené skupiny 
        $rozpocets =$this->database->table('rozpocet')->where('rok',$rok)->where('verze',$verze)->where('id',$skupina);
    
        $fmt = new \NumberFormatter( 'cs_CZ', \NumberFormatter::CURRENCY );
        $fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
        
        $fetchedRozpocets = [];
        foreach ($rozpocets as $rozpocet) {
            $item = new stdClass;
            $item->id = $rozpocet->id;
            $item->rozpocet = $rozpocet->rozpocet;
            $item->castkaRozpocet = $rozpocet->castka;
            $item->jmeno = $rozpocet->ref('hospodar')->jmeno;
            $item->jmeno2 = $rozpocet->ref('hospodar2')->jmeno;
            $item->castkaSablony = $rozpocet->sablony;

            $relevantni = $this->database->table('zakazky')->select('zakazka')->where('vlastni', 1); //    vlastní zakázky, které se počítají
            $utracenoV = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)->where('zakazky',$relevantni)
                                ->sum('castka');
            $utracenoV = \round($utracenoV, 0);
            
            // $objednavkyV_suma = $this->database->table('objednavky')->where('cinnost', ':cinnost.id_rozpocet')->where('zakazka',$relevantni)
            // ->where('stav ?', [0,1,3,4,9])->sum('castka');
           
            $rozpocetId = $rozpocet->id;
            $pomoc = $this->database->table('cinnost')->where('id_rozpocet',$rozpocetId);
            $relevantniCelaRadkaList = $this->database->table('zakazky')->select('id')->where('vlastni', 1)->fetchAll();
            
         bdump($relevantniCelaRadkaList);
            $objednavkyV_suma = $this->database->table('objednavky')->where('cinnost', $pomoc)->where('zakazka',$relevantniCelaRadkaList)
            ->where('stav ?', [0,1,3,4,9])->sum('castka');
            
            
           
            $objednavkyV_suma = \round($objednavkyV_suma, 0);     //    nezamítnuté vlastní objednávky na rozpočet - celková částka
            
            
            $item->mySumV = $utracenoV + $objednavkyV_suma;

            $item->rozdilV = $item->castkaRozpocet - ( $item->mySumV );

            $relevantniS = $this->database->table('zakazky')->select('zakazka')->where('sablony', 1); //    zakázky šablony, které se počítají
            $utracenoS = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)->where('zakazky',$relevantniS)
                                ->sum('castka');
            $utracenoS = \round($utracenoS, 0);
            
            $objednavkyS_suma = $this->database->table('objednavky')->where('cinnost', ':cinnost.id_rozpocet')->where('zakazka',$relevantniS)
            ->where('stav ?', [0,1,3,4,9])->sum('castka');
            $objednavkyS_suma = \round($objednavkyS_suma, 0);     //    nezamítnuté šablony objednávky na rozpočet - celková částka

            $item->mySumS = $utracenoS + $objednavkyS_suma;

            $item->rozdilS = $item->castkaSablony - ( $item->mySumS );

            $fetchedRozpocets[] = json_decode(json_encode($item), true);;
        }
        return $fetchedRozpocets;
    }

    public function createComponentSimpleGrid($name)      
    {
        $grid = new DataGrid($this, $name);
        $source = $this->mapRozpocet(1);
        $grid->setDataSource($source);
        $grid->addColumnLink('rozpocet', 'Rozpočet', 'Jeden:show', 'rozpocet', ['jedenId' => 'id']);
        $grid->addColumnText('jmeno', 'Hospodář');
        $grid->addColumnText('jmeno2', 'Zástupce');
        $grid->addColumnNumber('castkaRozpocet', 'Rozpočet - roční plán')->addCellAttributes(['class' => 'text-success']);
        $grid->addColumnNumber('mySumV', 'Rozpočet - čerpáno');
        $grid->addColumnNumber('rozdilV', 'Rozpočet - zbývá');
        $grid->addColumnNumber('castkaSablony', 'Šablony - roční plán')->addCellAttributes(['class' => 'text-success']);
        $grid->addColumnNumber('mySumS', 'Šablony - čerpáno');
        $grid->addColumnNumber('rozdilS', 'Šablony - zbývá');
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
        ->setTitle('Export do csv');
        $grid->setPagination(false);

        $translator = new \Ublaboo\DataGrid\Localization\SimpleTranslator([
            'ublaboo_datagrid.no_item_found_reset' => 'Žádné položky nenalezeny. Filtr můžete vynulovat',
            'ublaboo_datagrid.no_item_found' => 'Žádné položky nenalezeny.',
            'ublaboo_datagrid.here' => 'zde',
            'ublaboo_datagrid.items' => 'Položky',
            'ublaboo_datagrid.all' => 'všechny',
            'ublaboo_datagrid.from' => 'z',
            'ublaboo_datagrid.reset_filter' => 'Resetovat filtr',
            'ublaboo_datagrid.group_actions' => 'Hromadné akce',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Vyberte',
            'ublaboo_datagrid.execute' => 'Provést',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        $grid->setTranslator($translator);
    } 

    public function schvalitBtn(array $ids): void
    {
        $this->database->table('objednavky')->where('id',$ids)->where('nutno_overit',1)->update([
            'stav' => 1,
            'schvalil' => new DateTime(),
        ]);

        $uz = $this->prihlasenyId();
        $this->database->table('objednavky')->where('id',$ids)->where('nutno_overit',1)->where('kdo2',$uz)->update([
            'stav' => 4,
            'overil' => new DateTime(),
        ]);
        
        $this->database->table('objednavky')->where('id',$ids)->where('nutno_overit',0)->update([
            'stav' => 3,
            'schvalil' => new DateTime(),
        ]);

        if ($this->isAjax()) {
            $this->grids['schvalovaciGrid']->reload();
        } else {
            $this->redirect('this');
        }
    }

    public function zamitnoutBtn(array $ids): void
    {
        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 2,
            'zamitnul' => new DateTime(),
        ]);

        if ($this->isAjax()) {
            $this->grids['schvalovaciGrid']->reload();
        } else {
            $this->redirect('this');
        }
    }

    public function createComponentSchvalitGrid($name)
    {
        $uz = $this->prihlasenyId();   // přihlášený uživatel
        $grid = new DataGrid($this, $name);
        $this->grids['schvalovaciGrid'] = $grid;
        $source = $this->database->table('objednavky')->where('kdo', $uz)->where('stav', 0);
        $grid->setDataSource($source);
        $grid->addColumnText('id_prehled','Č. obj.');
        $grid->addColumnText('prehled_popis','Popis objednávky','prehled.popis:id_prehled');
        $grid->addColumnText('radka','Č. pol.');
        $grid->addColumnText('zakladatel','Zakladatel','uzivatel.jmeno:zakladatel' );
        $grid->addColumnText('firma','Firma');
        $grid->addColumnText('popis','Popis položky');
        $grid->addColumnText('cinnost','Činnost','cinnost.cinnost:cinnost');
        $grid->addColumnText('zakazka','Zakázka','zakazky.zakazka:zakazka');
        $grid->addColumnText('zakazkap','Popis zakázky','zakazky.popis:zakazka');
        $grid->addColumnText('stredisko','Středisko','stredisko.stredisko:stredisko');
        $grid->addColumnText('castka', 'Částka');
        $grid->addColumnText('nutno_overit', 'Nutné ověřit','nutno_overit');
        $grid->addColumnCallback('nutno_overit', function($column, $item) {
                $column->setRenderer(function() use ($item) {
                    return $item->nutno_overit == 1 ? "Určitě" : "Nikoli";   //TK: jen jako ukázka pro Magdu, následně změnit na "Ano" : "Ne"
                });
            });
        $grid->setPagination(false);
        $grid->addGroupAction('Schválit')->onSelect[] = [$this, 'schvalitBtn'];
        $grid->addGroupAction('Zamítnout')->onSelect[] = [$this, 'zamitnoutBtn'];
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(false);

        $translator = new \Ublaboo\DataGrid\Localization\SimpleTranslator([
            'ublaboo_datagrid.no_item_found_reset' => 'Žádné položky nenalezeny. Filtr můžete vynulovat',
            'ublaboo_datagrid.no_item_found' => 'Žádné položky nenalezeny.',
            'ublaboo_datagrid.here' => 'zde',
            'ublaboo_datagrid.items' => 'Položky',
            'ublaboo_datagrid.all' => 'všechny',
            'ublaboo_datagrid.from' => 'z',
            'ublaboo_datagrid.reset_filter' => 'Resetovat filtr',
            'ublaboo_datagrid.group_actions' => 'Vyberte objednávky',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Co chcete dělat?',
            'ublaboo_datagrid.execute' => 'Provést',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        $grid->setTranslator($translator);
    } 

    public function overitBtn(array $ids): void
    {
        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 4,
            'overil' => new DateTime(),
        ]);
        if ($this->isAjax()) {
            $this->grids['schvalovaciGrid']->reload();
        } else {
            $this->redirect('this');
        }
    }


    public function zamitnoutOvBtn(array $ids): void
    {
        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 5,
            'zamitnul2' => new DateTime(),
        ]);

        if ($this->isAjax()) {
            $this->grids['schvalovaciGrid']->reload();
        } else {
            $this->redirect('this');
        }
    }


    public function createComponentOveritGrid($name)
    {
        $uz = $this->prihlasenyId();   // přihlášený uživatel
        $grid = new DataGrid($this, $name);
        $this->grids['schvalovaciGrid'] = $grid;
        $source = $this->database->table('objednavky')->where('kdo2', $uz)->where('stav', 1);
        $grid->setDataSource($source);
        $grid->addColumnText('id_prehled','Č. obj.');
        $grid->addColumnText('prehled_popis','Popis objednávky','prehled.popis:id_prehled');
        $grid->addColumnText('radka','Č. pol.');
        $grid->addColumnText('zakladatel','Zakladatel','uzivatel.jmeno:zakladatel' );
        $grid->addColumnText('firma','Firma');
        $grid->addColumnText('popis','Popis položky');
        $grid->addColumnText('cinnost','Činnost','cinnost.cinnost:cinnost');
        $grid->addColumnText('zakazka','Zakázka','zakazky.zakazka:zakazka');
        $grid->addColumnText('zakazkap','Popis zakázky','zakazky.popis:zakazka');
        $grid->addColumnText('stredisko','Středisko','stredisko.stredisko:stredisko');
        $grid->addColumnText('castka', 'Částka');
        $grid->setPagination(false);
        $grid->addGroupAction('Ověřit')->onSelect[] = [$this, 'overitBtn'];
        $grid->addGroupAction('Zamítnout')->onSelect[] = [$this, 'zamitnoutOvBtn'];
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(false);

        $translator = new \Ublaboo\DataGrid\Localization\SimpleTranslator([
            'ublaboo_datagrid.no_item_found_reset' => 'Žádné položky nenalezeny. Filtr můžete vynulovat',
            'ublaboo_datagrid.no_item_found' => 'Žádné položky nenalezeny.',
            'ublaboo_datagrid.here' => 'zde',
            'ublaboo_datagrid.items' => 'Položky',
            'ublaboo_datagrid.all' => 'všechny',
            'ublaboo_datagrid.from' => 'z',
            'ublaboo_datagrid.reset_filter' => 'Resetovat filtr',
            'ublaboo_datagrid.group_actions' => 'Vyberte objednávky',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Co chcete dělat?',
            'ublaboo_datagrid.execute' => 'Provést',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        $grid->setTranslator($translator);
    } 
}



