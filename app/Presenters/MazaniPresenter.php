<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\GroupAction\GroupAction;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;


class MazaniPresenter extends ObjednavkyBasePresenter
{
    private $grids = [];

    protected function startup()
    {
        parent::startup();
    }

    public function renderShow(): void
	{
        // $uzivatel = $this->getUser()->getIdentity()->jmeno;      //   jméno uživatel
        $uz = $this->prihlasenyId();
        // $uz = $this->database->table('uzivatel')->where('jmeno',$uzivatel)->fetch();  //id prihlaseny uzivatel
        $this->template->prihlasen = ($this->database->table('uzivatel')->where('id',$uz)->fetch())->jmeno;
    }

    public function renderArch(): void
	{
        $uz = $this->prihlasenyId();
        $this->template->prihlasen = ($this->database->table('uzivatel')->where('id',$uz)->fetch())->jmeno;
    }

    public function deleteObj2(array $ids): void
    {
        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 7
        ]);
        if ($this->isAjax()) {
            $this->redirect('this');
            //$this->grids['mazaciGrid']->reload();
        } else {
            $this->redirect('this');
        }
    }
    
    private function mapRozpocet($argument,$aray )
    {
        $uz = $this->prihlasenyId();   // přihlášený uživatel
        $source = $this->database->table('objednavky')
                        ->where('zakladatel', $uz)->where('stav', $aray);
        $fetchedRozpocets = [];
        foreach ($source as $objednavky) {
            $item = new stdClass;
            $item->id = $objednavky->id;
            $item->id_prehled = $objednavky->id_prehled;
            $item->radka = $objednavky->radka;
            $pomoc =  $this->database->table('objednavky')->where('id_prehled',$item->id_prehled )->fetch();
            // $pom2 = $this->database->table('prehled')->where('id',$pomoc->id_prehled)->fetch();
            // $pom3 = $this->database->table('uzivatel')->where('id',$pom2->id_uzivatel)->fetch();
            // $item->zadavatel =$pom3->jmeno ;
            $item->zadavatel = $objednavky->ref('zakladatel')->jmeno;
            $item->schvalovatel = $objednavky->ref('kdo')->jmeno;
            $item->schvalil = $objednavky->schvalil;
            $item->overovatel = $objednavky->ref('kdo2')->jmeno;
            $item->overil = $objednavky->overil;             
            $item->nutno_overit = $objednavky->ref('nutno_overit')->popis;
            $item->stav = $objednavky->ref('stav')->popis;
            $item->firma = $objednavky->firma;
            $item->popis = $objednavky->popis;
            $item->cinnost = $objednavky->ref('cinnost')->cinnost;
            $item->cinnostP = $objednavky->ref('cinnost')->nazev_cinnosti;
            $item->zakazka = $objednavky->ref('zakazka')->zakazka;
            $item->stredisko = $objednavky->ref('stredisko')->stredisko;
            $item->castka = $objednavky->castka;
            $fetchedRozpocets[] = $item;
        }
        return $fetchedRozpocets;
    }
    
    public function createComponentSimpleGrid2($name)
    {
        $grid = new DataGrid($this, $name);
        $this->grids['mazaciGrid'] = $grid;
        bdump($this->grids);
        $source = $this->mapRozpocet(1,[0,1,3,4]);
        $grid->setDataSource($source);
        $grid->addColumnText('id_prehled','Č. obj.');
        $grid->addColumnText('radka','Č. pol.');
        $grid->addColumnText('zadavatel','Zadavatel');
        $grid->addColumnText('stav','stav objednávky');
        $grid->addColumnText('schvalovatel','Schvalovatel');
        $grid->addColumnDateTime('schvalil','Schváleno');
        $grid->addColumnText('nutno_overit','Nutno ověřit');
        $grid->addColumnText('overovatel','Ověřovatel');
        $grid->addColumnDateTime('overil','Ověřeno');
        $grid->addColumnText('firma','firma');
        $grid->addColumnText('popis','popis');
        $grid->addColumnText('cinnost','Činnost');
        $grid->addColumnText('cinnostP','Popis činnosti');
        $grid->addColumnText('zakazka','Zakázka');
        $grid->addColumnText('stredisko','Středisko');
        $grid->addColumnNumber('castka', 'Částka');
        $grid->setPagination(false);
        $grid->addGroupAction('Ano - smazat!')->onSelect[] = [$this, 'deleteObj2'];
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
            'ublaboo_datagrid.group_actions' => 'Zaškrtni objednávky ke smazání. .',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Opravdu smazat?"',
            'ublaboo_datagrid.execute' => 'Smazat vybrané objednávky!',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        $grid->setTranslator($translator);
    } 

    public function deleteObj3(array $ids): void
    {
        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 7
        ]);
        if ($this->isAjax()) {
            $this->redirect('this');
            //$this->grids['zamitGrid']->reload();
        } else {
            $this->redirect('this');
        }
    }

    public function createComponentZamitGrid2($name)
    {
        $grid = new DataGrid($this, $name);
        $this->grids['zamitGrid'] = $grid;
        $source = $this->mapRozpocet(1, [2,5,8]);
        $grid->setDataSource($source);
        $grid->addColumnText('id_prehled','Č. obj.');
        $grid->addColumnText('radka','Č. pol.');
        $grid->addColumnText('zadavatel','Zadavatel');
        $grid->addColumnText('stav','stav objednávky');
        $grid->addColumnText('schvalovatel','Schvalovatel');
        $grid->addColumnDateTime('schvalil','Schváleno');
        $grid->addColumnText('nutno_overit','Nutno ověřit');
        $grid->addColumnText('overovatel','Ověřovatel');
        $grid->addColumnDateTime('overil','Ověřeno');
        $grid->addColumnText('firma','firma');
        $grid->addColumnText('popis','popis');
        $grid->addColumnText('cinnost','Činnost');
        $grid->addColumnText('cinnostP','Popis činnosti');
        $grid->addColumnText('zakazka','Zakázka');
        $grid->addColumnText('stredisko','Středisko');
        $grid->addColumnNumber('castka', 'Částka');
        $grid->setPagination(false);
        $grid->addGroupAction('Ano - smazat!')->onSelect[] = [$this, 'deleteObj3'];
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
            'ublaboo_datagrid.group_actions' => 'Zaškrtni objednávky ke smazání. .',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Opravdu smazat?"',
            'ublaboo_datagrid.execute' => 'Smazat vybrané objednávky!',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        $grid->setTranslator($translator);
    } 

    public function createComponentArchivGrid2($name)
    {
        $grid = new DataGrid($this, $name);
        $this->grids['archivGrid'] = $grid;
        $source = $this->mapRozpocet(1, [0,1,3,4,9]);
        $grid->setDataSource($source);     
        $grid->addColumnText('id_prehled','Č. obj.');
        $grid->addColumnText('radka','Č. pol.');
        $grid->addColumnText('zadavatel','Zadavatel');
        $grid->addColumnText('stav','stav objednávky');
        $grid->addColumnText('schvalovatel','Schvalovatel');
        $grid->addColumnDateTime('schvalil','Schváleno');
        $grid->addColumnText('nutno_overit','Nutno ověřit');
        $grid->addColumnText('overovatel','Ověřovatel');
        $grid->addColumnDateTime('overil','Ověřeno');
        $grid->addColumnText('firma','firma');
        $grid->addColumnText('popis','popis');
        $grid->addColumnText('cinnost','Činnost');
        // $grid->addColumnText('cinnostP','Popis činnosti');
        $grid->addColumnText('zakazka','Zakázka');
        $grid->addColumnText('stredisko','Středisko');
        $grid->addColumnNumber('castka', 'Částka');
        $grid->setPagination(false);
        // $grid->addGroupAction('Ano - smazat!')->onSelect[] = [$this, 'deleteObj3'];
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
        ->setTitle('Export do csv');
        $grid->setPagination(true);
        $grid->setItemsPerPageList([10, 20, 50]);

        $translator = new \Ublaboo\DataGrid\Localization\SimpleTranslator([
            'ublaboo_datagrid.no_item_found_reset' => 'Žádné položky nenalezeny. Filtr můžete vynulovat',
            'ublaboo_datagrid.no_item_found' => 'Žádné položky nenalezeny.',
            'ublaboo_datagrid.here' => 'zde',
            'ublaboo_datagrid.items' => 'Položky',
            'ublaboo_datagrid.all' => 'všechny',
            'ublaboo_datagrid.from' => 'z',
            'ublaboo_datagrid.reset_filter' => 'Resetovat filtr',
            'ublaboo_datagrid.group_actions' => 'Zaškrtni objednávky ke smazání. .',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Opravdu smazat?"',
            'ublaboo_datagrid.execute' => 'Smazat vybrané objednávky!',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        $grid->setTranslator($translator);
    } 

    public function createComponentArchivGrid3($name)
    {
        $grid = new DataGrid($this, $name);
        $this->grids['archivGrid'] = $grid;
        $source = $this->mapRozpocet(1, [2,5,8]);
        $grid->setDataSource($source);     
        $grid->addColumnText('id_prehled','Číslo objednávky');
        $grid->addColumnText('radka','Číslo položky');
        $grid->addColumnText('zadavatel','Zadavatel');
        $grid->addColumnText('stav','stav objednávky');
        $grid->addColumnText('schvalovatel','Schvalovatel');
        $grid->addColumnDateTime('schvalil','Schváleno');
        $grid->addColumnText('nutno_overit','Nutno ověřit');
        $grid->addColumnText('overovatel','Ověřovatel');
        $grid->addColumnDateTime('overil','Ověřeno');
        $grid->addColumnText('firma','firma');
        $grid->addColumnText('popis','popis');
        $grid->addColumnText('cinnost','Činnost');
        // $grid->addColumnText('cinnostP','Popis činnosti');
        $grid->addColumnText('zakazka','Zakázka');
        $grid->addColumnText('stredisko','Středisko');
        $grid->addColumnNumber('castka', 'Částka');
        $grid->setPagination(false);
        // $grid->addGroupAction('Ano - smazat!')->onSelect[] = [$this, 'deleteObj3'];
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(true);
        $grid->setItemsPerPageList([10, 20, 50]);

        $translator = new \Ublaboo\DataGrid\Localization\SimpleTranslator([
            'ublaboo_datagrid.no_item_found_reset' => 'Žádné položky nenalezeny. Filtr můžete vynulovat',
            'ublaboo_datagrid.no_item_found' => 'Žádné položky nenalezeny.',
            'ublaboo_datagrid.here' => 'zde',
            'ublaboo_datagrid.items' => 'Položky',
            'ublaboo_datagrid.all' => 'všechny',
            'ublaboo_datagrid.from' => 'z',
            'ublaboo_datagrid.reset_filter' => 'Resetovat filtr',
            'ublaboo_datagrid.group_actions' => 'Zaškrtni objednávky ke smazání. .',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Opravdu smazat?"',
            'ublaboo_datagrid.execute' => 'Smazat vybrané objednávky!',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        $grid->setTranslator($translator);
    } 

    public function createComponentArchivGrid4($name)
    {
        $grid = new DataGrid($this, $name);
        $this->grids['archivGrid'] = $grid;
        $source = $this->mapRozpocet(1, [6]);
        $grid->setDataSource($source);     
        $grid->addColumnText('id_prehled','Číslo objednávky');
        $grid->addColumnText('radka','Číslo položky');
        $grid->addColumnText('zadavatel','Zadavatel');
        $grid->addColumnText('stav','stav objednávky');
        $grid->addColumnText('schvalovatel','Schvalovatel');
        $grid->addColumnDateTime('schvalil','Schváleno');
        $grid->addColumnText('nutno_overit','Nutno ověřit');
        $grid->addColumnText('overovatel','Ověřovatel');
        $grid->addColumnDateTime('overil','Ověřeno');
        $grid->addColumnText('firma','firma');
        $grid->addColumnText('popis','popis');
        $grid->addColumnText('cinnost','Činnost');
        // $grid->addColumnText('cinnostP','Popis činnosti');
        $grid->addColumnText('zakazka','Zakázka');
        $grid->addColumnText('stredisko','Středisko');
        $grid->addColumnNumber('castka', 'Částka');
        $grid->setPagination(false);
        // $grid->addGroupAction('Ano - smazat!')->onSelect[] = [$this, 'deleteObj3'];
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(true);
        $grid->setItemsPerPageList([10, 20, 50]);

        $translator = new \Ublaboo\DataGrid\Localization\SimpleTranslator([
            'ublaboo_datagrid.no_item_found_reset' => 'Žádné položky nenalezeny. Filtr můžete vynulovat',
            'ublaboo_datagrid.no_item_found' => 'Žádné položky nenalezeny.',
            'ublaboo_datagrid.here' => 'zde',
            'ublaboo_datagrid.items' => 'Položky',
            'ublaboo_datagrid.all' => 'všechny',
            'ublaboo_datagrid.from' => 'z',
            'ublaboo_datagrid.reset_filter' => 'Resetovat filtr',
            'ublaboo_datagrid.group_actions' => 'Zaškrtni objednávky ke smazání. .',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Opravdu smazat?"',
            'ublaboo_datagrid.execute' => 'Smazat vybrané objednávky!',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        $grid->setTranslator($translator);
    } 

}