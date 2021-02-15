<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\GroupAction\GroupAction;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;


class UctarnaPresenter extends ObjednavkyBasePresenter
{
    private $grids = [];

    protected function startup()
    {
        parent::startup();
        $mojerole = $this->getUser()->getRoles();
        if (empty($mojerole))
        {
            $this->redirect('Homepage:default');
            $this->flashMessage('Nemáte oprávnění.');
        } 
    }

    public function renderShow(): void
	{
        // $uzivatel = $this->getUser()->getIdentity()->jmeno;      //   jméno uživatel
        $uz = $this->prihlasenyId();
        // $uz = $this->database->table('uzivatel')->where('jmeno',$uzivatel)->fetch();  //id prihlaseny uzivatel
        $this->template->prihlasen = ($this->database->table('uzivatel')->where('id',$uz)->fetch())->jmeno;
    }

    public function deleteObj2(array $ids): void
    {
        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 8
        ]);
        $this->redirect('this');
    }

    public function deleteOdl(array $ids): void
    {
        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 9
        ]);
        $this->redirect('this');
    }
    
    private function mapRozpocet($argument)
    {
        $uz = $this->prihlasenyId();   // přihlášený uživatel
        $source = $this->database->table('objednavky')
           ->where('stav', [3,4]);
        // $source = $this->database->table('objednavky')->where('stav', [3,4])->select('DISTINCT id_prehled');

        $fetchedRozpocets = []; //iniciace promene pred blokem cyklu

        foreach ($source as $objednavky) {
            $item = new stdClass;
            $item->id = $objednavky->id;
            $item->id_prehled = $objednavky->id_prehled;
            $item->radka = $objednavky->radka;
            $pomoc =  $this->database->table('objednavky')->where('id_prehled',$item->id_prehled )->fetch();
            // $pom2 = $this->database->table('prehled')->where('id',$pomoc->id_prehled)->fetch();
            // $pom3 = $this->database->table('uzivatel')->where('id',$pom2->id_uzivatel)->fetch();
            // bdump($pom2);
            // $item->zadavatel =$pom3->jmeno ;
            $item->zadavatel = $objednavky->ref('zakladatel')->jmeno;
            $item->schvalovatel = $objednavky->ref('kdo')->jmeno;
            $item->schvalil = $objednavky->schvalil;
            $item->overovatel = $objednavky->ref('kdo2')->jmeno;
            $item->overil = $objednavky->overil;             
            $item->nutno_overit = $objednavky->nutno_overit;
            $item->stav = $objednavky->ref('stav')->popis;
            $item->firma = $objednavky->firma;
            $item->popis = $objednavky->popis;
            $item->cinnost = $objednavky->ref('cinnost')->cinnost;
            $item->cinnostP = $objednavky->ref('cinnost')->nazev_cinnosti;
            $item->zakazka = $objednavky->ref('zakazka')->zakazka;
            $item->stredisko = $objednavky->ref('stredisko')->stredisko;
            $item->castka = $objednavky->castka;
            $item->hasChildren = true;
            $fetchedRozpocets[] = $item;
          
        }
       
        return $fetchedRozpocets;
    }

    //tady do item namapuj jenom to co potrebujes v nadpisu + si muzes do castky udelat soucet
    private function namapujParenta($idPrehled) //id prehled je cislo int
    {
        $objednavky = $this->database->table('objednavky')->where('id_prehled', $idPrehled)->fetch();

        $item = new stdClass;
        $item->id = $objednavky->id_prehled;
        $item->id_prehled = $objednavky->id_prehled;
        $item->radka = ''; //$objednavky->radka;
        $pomoc =  $this->database->table('objednavky')->where('id_prehled',$item->id_prehled )->fetch();
        // $pom2 = $this->database->table('prehled')->where('id',$pomoc->id_prehled)->fetch();
        // $pom3 = $this->database->table('uzivatel')->where('id',$pom2->id_uzivatel)->fetch();
        // bdump($pom2);
        // $item->zadavatel =$pom3->jmeno ;
        $item->zadavatel = $objednavky->ref('zakladatel')->jmeno;
        $item->schvalovatel = $objednavky->ref('kdo')->jmeno;
        $item->schvalil = $objednavky->schvalil;
        $item->overovatel = $objednavky->ref('kdo2')->jmeno;
        $item->overil = $objednavky->overil;             
        $item->nutno_overit = $objednavky->nutno_overit;
        $item->stav = $objednavky->ref('stav')->popis;
        $item->firma = $objednavky->firma;
        $item->popis = '';
        $item->cinnost = $objednavky->ref('cinnost')->cinnost;
        $item->cinnostP = $objednavky->ref('cinnost')->nazev_cinnosti;
        $item->zakazka = $objednavky->ref('zakazka')->zakazka;
        $item->stredisko = $objednavky->ref('stredisko')->stredisko;
        $item->castka =  $this->database->table('objednavky')->where('id_prehled', $idPrehled)->sum('castka');
        
        $item->hasChildren = true; //dulezite, znamena, ze polozka se muze rozbalit

        return $item;
    }

    //tady se mapujou childi nebo parent s jednou polozkou - zmanena, namapuj do item-> co nejvic veci co potrebujes
    private function namapujChildaNeboParentaKterejMaJenomJednuPolozku($idRadky)
    {
        $objednavky = $this->database->table('objednavky')->where('id', $idRadky)->fetch();
        bdump($objednavky);
        $item = new stdClass;
        $item->id = $idRadky; //tohle podelany id musi byt v tom datagridu jine pro kazdy child, jinak je sam nejak spoji dohromady a zmrsi data, takze jsme to minule meli dobre, jenom stacilo zmenit tohle id
        $item->id_prehled = $objednavky->id_prehled;
        $item->radka = $objednavky->radka;
        $pomoc =  $this->database->table('objednavky')->where('id_prehled',$item->id_prehled )->fetch();
        // $pom2 = $this->database->table('prehled')->where('id',$pomoc->id_prehled)->fetch();
        // $pom3 = $this->database->table('uzivatel')->where('id',$pom2->id_uzivatel)->fetch();
        // bdump($pom2);
        // $item->zadavatel =$pom3->jmeno ;
        $item->zadavatel = $objednavky->ref('zakladatel')->jmeno;
        $item->schvalovatel = $objednavky->ref('kdo')->jmeno;
        $item->schvalil = $objednavky->schvalil;
        $item->overovatel = $objednavky->ref('kdo2')->jmeno;
        $item->overil = $objednavky->overil;             
        $item->nutno_overit = $objednavky->nutno_overit;
        $item->stav = $objednavky->ref('stav')->popis;
        $item->firma = $objednavky->firma;
        $item->popis = $objednavky->popis;
        $item->cinnost = $objednavky->ref('cinnost')->cinnost;
        $item->cinnostP = $objednavky->ref('cinnost')->nazev_cinnosti;
        $item->zakazka = $objednavky->ref('zakazka')->zakazka;
        $item->stredisko = $objednavky->ref('stredisko')->stredisko;
        $item->castka = $objednavky->castka;
        
        $item->hasChildren = false; //dulezite, znamena, ze polozka se muze rozbalit

        return $item;
    }

    private function ziskejDatagridSource() //to jsou parenti
    {
        $uz = $this->prihlasenyId();   // přihlášený uživatel
        $idPrehledy = $this->database->table('objednavky')->where('stav', [1,2,3,4,5,6,7,8,9])->select('DISTINCT id_prehled');
        $fetchedRozpocets = []; //iniciace promene pred blokem cyklu

        foreach ($idPrehledy as $idPrehled) {
            $skupinaCount = $this->database->table('objednavky')->where('id_prehled', $idPrehled->id_prehled)->max('radka');

            if($skupinaCount > 1)
            {
                $fetchedRozpocets[] = $this->namapujParenta($idPrehled->id_prehled);
            }
            else
            {
                $objednavka = $this->database->table('objednavky')->where('id_prehled', $idPrehled->id_prehled)->fetch();
                $fetchedRozpocets[] = $this->namapujChildaNeboParentaKterejMaJenomJednuPolozku($objednavka->id);
            }
        }

        return $fetchedRozpocets;
    }

    public function getChildren($parentId) {
        $uz = $this->prihlasenyId();   // přihlášený uživatel
        $source = $this->database->table('objednavky')->where('id_prehled', $parentId)->fetchAll();
           //->where('stav', [3,4])

        // $source = $this->database->table('objednavky')->where('stav', [3,4])->select('DISTINCT id_prehled');

        $fetchedRozpocets = []; //iniciace promene pred blokem cyklu

        foreach ($source as $objednavka) {
            $newItem = $this->namapujChildaNeboParentaKterejMaJenomJednuPolozku($objednavka->id);
            $fetchedRozpocets[] = $newItem;
        }

        bdump($fetchedRozpocets);
        return $fetchedRozpocets;
    }

    public function hasChildren($parentId) {
        return $this->database->table('objednavky')->where('id_prehled',$parentId)->count() > 0 ? true : false;
    }
    
    public function createComponentSimpleGrid2($name)
    {
        $grid = new DataGrid($this, $name);
        $this->grids['mazaciGrid'] = $grid;
        $grid->setTreeView([$this, 'getChildren'], 'hasChildren');

        $source = $this->ziskejDatagridSource(1);
        //$source = $this->database->table('objednavky');


        $grid->setDataSource($source);
        $grid->addColumnText('id_prehled','Číslo objednávky');
        $grid->addColumnText('radka','Číslo položky');
        $grid->addColumnNumber('castka', 'Částka jednotlivé položky');
        // $grid->addColumnText('zadavatel','Zadavatel');
        // $grid->addColumnText('stav','stav objednávky');
        // $grid->addColumnText('schvalovatel','Schvalovatel');
        // $grid->addColumnDateTime('schvalil','Schváleno');
         $grid->addColumnText('nutno_overit','Nutno ověřit');
         $grid->addColumnCallback('nutno_overit', function($column, $item) {
            $column->setRenderer(function() use ($item) {
                return $item->nutno_overit == 1 ? "ANO" : "ne";   
            });
        });
        // $grid->addColumnText('overovatel','Ověřovatel');
         $grid->addColumnDateTime('overil','Ověřeno');
         $grid->addColumnText('firma','firma');
         $grid->addColumnText('popis','popis');
         $grid->addColumnText('cinnost','Činnost');
        // $grid->addColumnText('cinnostP','Popis činnosti');
        // $grid->addColumnText('zakazka','Zakázka');
        // $grid->addColumnText('stredisko','Středisko');
        // $grid->setPagination(false);
        // $grid->addGroupAction('Zpracovat - zmizí ze seznamu')->onSelect[] = [$this, 'deleteOdl'];
        // $grid->addGroupAction('Smazat - nebude se realizovat')->onSelect[] = [$this, 'deleteObj2'];
        // // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // // ->setTitle('Export do csv s filtrem');
        // $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
        //     ->setTitle('Export do csv');
        // $grid->setPagination(false);

        $translator = new \Ublaboo\DataGrid\Localization\SimpleTranslator([
            'ublaboo_datagrid.no_item_found_reset' => 'Žádné položky nenalezeny. Filtr můžete vynulovat',
            'ublaboo_datagrid.no_item_found' => 'Žádné položky nenalezeny.',
            'ublaboo_datagrid.here' => 'zde',
            'ublaboo_datagrid.items' => 'Položky',
            'ublaboo_datagrid.all' => 'všechny',
            'ublaboo_datagrid.from' => 'z',
            'ublaboo_datagrid.reset_filter' => 'Resetovat filtr',
            'ublaboo_datagrid.group_actions' => 'Vyber objednávky',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Vyber činnost"',
            'ublaboo_datagrid.execute' => 'Vykonej',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        $grid->setTranslator($translator);
    } 
}