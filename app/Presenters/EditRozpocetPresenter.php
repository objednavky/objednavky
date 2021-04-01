<?php
//Editace parametrů detailního rozpočtu


declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Utils;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;

class EditRozpocetPresenter extends ObjednavkyBasePresenter
{

    private $sessionSection;

    protected function startup()
    {
        parent::startup();
        if (!isset($this->sessionSection)) {
            $this->sessionSection = $this->getSession('EditDetailPresenter');
        }
    }

    public function actionDefault(?int $rok, ?int $verze): void
	{
        bdump('renderDefault');
        $setup = $this->getSetup(1);

        if (isset($rok) && in_array($rok, $this->nactiRoky())) {
            $this->sessionSection->rok = $rok;
        } elseif (isset($this->sessionSection->rok)) {
            $rok = $this->sessionSection->rok;
        } else {
            $rok = $setup->rok;
            $this->sessionSection->rok = $setup->rok;
        }

        if (isset($verze) && in_array($verze, $this->nactiVerze($rok))) {
            $this->sessionSection->verze = $verze;
        } elseif (isset($this->sessionSection->verze) && in_array($verze, $this->nactiVerze($rok))) {
            $verze = $this->sessionSection->verze;
        } else {
            $verze = $setup->verze;
            $this->sessionSection->verze = $setup->verze;
        }
        $this->template->rok = $rok;
        $this->template->verze = $verze;

        bdump($rok);
        bdump($verze);

    }

    public function createComponentRokVerzeForm($name) {
        bdump('createComponentRokVerzeForm');
        $form = new Form($this, $name);
        //$form->onRender[] = '\App\Utils\FormStylePomocnik::makeBootstrap4Rozpocet';
        $form->addSelect('rok', 'Rok:',$this->nactiRoky())
            ->setDefaultValue($this->sessionSection->rok)
            ->setHtmlAttribute('class','rform-control');
        $form->addSelect('verze', 'Verze:',$this->nactiVerze($this->sessionSection->rok))
            ->setDefaultValue($this->sessionSection->verze)
            ->setHtmlAttribute('class','rform-control');
        $form->onSuccess[] = [$this, 'zmenRokyVerzeOnSubmit'];
    }

    public function createComponentEditRozpocetGrid($name) {
        bdump('createComponentEditRozpocetGrid');
        $grid = new DataGrid($this, $name);
        $source = $this->database->table('rozpocet')->where('rok',$this->sessionSection->rok)->where('verze',$this->sessionSection->verze);
        //$this->sessionSection->source = $source;
        $grid->setDataSource($source);
        bdump($grid);


        $grid->addColumnNumber('id', 'Id')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('rozpocet', 'Rozpočet')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('rok', 'Rok')->setAlign('right')->setSortable()->setSortableResetPagination()->setDefaultHide();
        $grid->addColumnNumber('verze', 'Verze')->setAlign('right')->setSortable()->setSortableResetPagination()->setDefaultHide();
        $grid->addColumnNumber('castka', 'Plán vlastní Kč')->setAlign('right')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnNumber('sablony', 'Plán šablony Kč')->setAlign('right')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('hospodar', 'Hospodář', 'hospodar.jmeno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('hospodar2', 'Zástupce', 'hospodar2.jmeno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->setColumnsSummary(['castka','sablony']);
        $grid->addExportCsv('Export do csv', 'rozpocet.csv', 'Windows-1250')->setTitle('Export do csv');
        $grid->setColumnsHideable();

        $grid->addInlineEdit()
        ->onControlAdd[] = function(Nette\Forms\Container $container): void {
            $container->addText('castka', '');
            $container->addText('sablony', '');
            $container->addSelect('hospodar','Hospodář:',$this->database->table('uzivatel')->select('id, jmeno')->order('jmeno')->fetchPairs('id','jmeno'));
            $container->addSelect('hospodar2','Zástupce:',$this->database->table('uzivatel')->select('id, jmeno')->order('jmeno')->fetchPairs('id','jmeno'));
        };
    
        $grid->getInlineEdit()->onSetDefaults[] = function(Nette\Forms\Container $container, $item): void {
            $container->setDefaults([
                'castka' => $item->castka,
                'sablony' => $item->sablony,
                'hospodar' => $item->hospodar,
                'hospodar2' => $item->hospodar2,
            ]);
        };
        
        $grid->getInlineEdit()->onSubmit[] = function($id, Nette\Utils\ArrayHash $values): void {
            if ($id) {
                $this->database->table('rozpocet')->where('id',$id)->update($values);
            }
        };
        $grid->getInlineEdit()->setShowNonEditingColumns();
        
        $grid->setPagination(true);
        $grid->setItemsPerPageList([10, 30, 100]);

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

    /**
     * zpracuje data přijatá po odeslání formuláře s novou objednávkou
     */
    public function zmenRokyVerzeOnSubmit(Form $form, $data): void
    {
        bdump('zmenRokyVerzeOnSubmit');

        //$this->sessionSection->rok = $data['rok'];
        //$this->sessionSection->verze = $data['verze'];
        $this->redirect("this", ['rok' => $data['rok'], 'verze' => $data['verze']]);
    }

}



