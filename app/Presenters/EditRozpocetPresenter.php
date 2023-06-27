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
use App\Model\ObjednavkyManager;
use Exception;

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
        } elseif (in_array($setup->verze, $this->nactiVerze($rok))) {
            $verze = $setup->verze;
            $this->sessionSection->verze = $setup->verze;
        } else {
            $verze = 1;
            $this->sessionSection->verze = 1;
        }
        $this->template->rok = $rok;
        $this->template->verze = $verze;

        bdump($rok);
        bdump($verze);

    }

    public function actionKopirujVerzi(): void
	{
        bdump('actionKopirujVerzi');
        $setup = $this->getSetup(1);

        $rok = $setup->rok;
        $verze = $setup->verze;

        bdump($rok);
        bdump($verze);

        $novaVerze = $this->objednavkyManager->vytvorNovouVerziRozpoctu($rok);

        if ($novaVerze) {
            $this->flashMessage('Nová verze rozpočtu roku ' . $rok . ' číslo ' . $novaVerze . ' byla úspěšně založena a aktivována. ','success');
        } else {
            $this->flashMessage('Novou verzi rozpočtu roku ' . $rok . ' se nepodařilo založit.','danger');
        }

        $this->redirect('default');

    }

    public function actionKopirujRok(): void
	{
        bdump('actionKopirujRok');
        $setup = $this->getSetup(1);

        $rok = $setup->rok;
        $verze = $setup->verze;

        bdump($rok);
        bdump($verze);

        $novyRok = $this->objednavkyManager->vytvorNovyRokRozpoctu($rok);

        if ($novyRok) {
            $this->flashMessage('Nový rok rozpočtu ' . $novyRok . ' byl úspěšně založen a byl do něj zkopírován aktuální rozpočet. Zatím nebyl aktivován, to je nutné provést ručně. ','success');
        } else {
            $this->flashMessage('Nový rok rozpočtu ' . $novyRok . ' se nepodařilo založit.','danger');
        }

        $this->redirect('default');

    }

    public function createComponentRokVerzeForm($name) {
        bdump('createComponentRokVerzeForm');
        $form = new Form($this, $name);
        //$form->onRender[] = '\App\Utils\FormStylePomocnik::makeBootstrap4Rozpocet';
        $form->addSelect('rok', 'Rok:',$this->nactiRoky())
            ->setDefaultValue($this->sessionSection->rok)
            ->setHtmlAttribute('class','rform-control');
        $form->addSelect('verze', 'Verze:',$this->nactiVerze($this->sessionSection->rok))
            ->setHtmlAttribute('class','rform-control')
            ->setDefaultValue($this->sessionSection->verze);
        $form->onSuccess[] = [$this, 'zmenRokyVerzeOnSubmit'];
    }

    public function createComponentEditRozpocetGrid($name) {
        bdump('createComponentEditRozpocetGrid');
        $grid = new DataGrid($this, $name);
        $rok = $this->sessionSection->rok;
        $verze = $this->sessionSection->verze;
        $source = $this->database->table('rozpocet')->where('rok', $rok)->where('verze',$verze);
        //$this->sessionSection->source = $source;
        $grid->setDataSource($source);
        bdump($grid);


        $grid->addColumnNumber('id', 'Id')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('cinnost_hlavni', 'Hl.činnost')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('rozpocet', 'Rozpočet')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('obsah', 'Popis')->setSortable()->setSortableResetPagination();
        $grid->addColumnNumber('castka', 'Plán vlastní Kč')->setAlign('right')->setSortable()->setSortableResetPagination();
        $grid->addColumnNumber('sablony', 'Plán šablony Kč')->setAlign('right')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('hospodar', 'Hospodář', 'hospodar.jmeno')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('hospodar2', 'Zástupce', 'hospodar2.jmeno')->setSortable()->setSortableResetPagination();
        $grid->addColumnNumber('overeni', 'Ověření Kč')->setAlign('right')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('overovatel', 'Ověřovatel', 'overovatel.jmeno')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('hezky', 'Hezký rozpočet', 'hezky.hezky_rozpocet')->setSortable()->setSortableResetPagination();
        $grid->setColumnsSummary(['castka','sablony']);
        $grid->addExportCsv('Export do csv', 'rozpocet.csv', 'Windows-1250')->setTitle('Export do csv');

        $grid->addInlineEdit()
        ->onControlAdd[] = function(Nette\Forms\Container $container): void {
            $container->addText('cinnost_hlavni', '')->setRequired('Uveďte zkratku činnosti');
            $container->addText('rozpocet', '')->setRequired('Uveďte název rozpočtu');
            $container->addText('obsah', '')->setRequired('Uveďte popis rozpočtu.');
            $container->addInteger('castka', '')->setRequired('Zadejte částku rozpočtu pro vlastní' );
            $container->addInteger('sablony', '')->setRequired('Zadejte částku rozpočtu pro šablony' );
            $container->addSelect('hospodar','Hospodář:',$this->database->table('uzivatel')->select('id, jmeno')->order('jmeno')->fetchPairs('id','jmeno'))->setRequired('Uveďte hospodáře rozpočtu.');
            $container->addSelect('hospodar2','Zástupce:',$this->database->table('uzivatel')->select('id, jmeno')->order('jmeno')->fetchPairs('id','jmeno'))->setRequired('Uveďte zástupce hospodáře rozpočtu.');
            $container->addInteger('overeni', '')->setRequired('Zadejte limit pro ověření' );
            $container->addSelect('overovatel','Ověrovatel:',$this->database->table('uzivatel')->select('id, jmeno')->order('jmeno')->fetchPairs('id','jmeno'))->setRequired('Uveďte ověřovatele rozpočtu.');
            $container->addSelect('hezky','Hezký rozpočet:',$this->database->table('hezky')->select('id, hezky_rozpocet')->order('id')->fetchPairs('id','hezky_rozpocet'))->setRequired('Přiřaďte hezký rozpočet.');
        };
    
        $grid->getInlineEdit()->onSetDefaults[] = function(Nette\Forms\Container $container, $item): void {
            $container->setDefaults([
                'cinnost_hlavni' => $item->cinnost_hlavni,
                'rozpocet' => $item->rozpocet,
                'obsah' => $item->obsah,
                'castka' => $item->castka,
                'sablony' => $item->sablony,
                'hospodar' => $item->hospodar,
                'hospodar2' => $item->hospodar2,
                'overeni' => $item->overeni,
                'overovatel' => $item->overovatel,
                'hezky' => $item->hezky,
            ]);
        };
        
        $grid->getInlineEdit()->onSubmit[] = function($id, Nette\Utils\ArrayHash $values): void {
            if ($id) {
                $this->database->table('rozpocet')->where('id',$id)->update($values);
            }
        };
        $grid->getInlineEdit()->setShowNonEditingColumns();
        
        $grid->addInlineAdd()
        ->onControlAdd[] = function(Nette\Forms\Container $container) use ($rok, $verze) : void {
            $container->addText('cinnost_hlavni', '')->setRequired('Uveďte zkratku činnosti');
            $container->addText('rozpocet', '')->setRequired('Uveďte název rozpočtu');
            $container->addText('obsah', '')->setRequired('Uveďte popis rozpočtu.');
            $container->addInteger('castka', '')->setRequired('Zadejte částku rozpočtu pro vlastní' );
            $container->addInteger('sablony', '')->setRequired('Zadejte částku rozpočtu pro šablony' );
            $container->addSelect('hospodar','Hospodář:',$this->database->table('uzivatel')->select('id, jmeno')->order('jmeno')->fetchPairs('id','jmeno'))->setRequired('Uveďte hospodáře rozpočtu.');
            $container->addSelect('hospodar2','Zástupce:',$this->database->table('uzivatel')->select('id, jmeno')->order('jmeno')->fetchPairs('id','jmeno'))->setRequired('Uveďte zástupce hospodáře rozpočtu.');
            $container->addInteger('overeni', '')->setRequired('Zadejte limit pro ověření' );
            $container->addSelect('overovatel','Ověrovatel:',$this->database->table('uzivatel')->select('id, jmeno')->order('jmeno')->fetchPairs('id','jmeno'))->setRequired('Uveďte ověřovatele rozpočtu.');
            $container->addSelect('hezky','Hezký rozpočet:',$this->database->table('hezky')->select('id, hezky_rozpocet')->order('id')->fetchPairs('id','hezky_rozpocet'))->setRequired('Přiřaďte hezký rozpočet.');
        };

        $grid->getInlineAdd()->onSubmit[] = function(Nette\Utils\ArrayHash $values) use ($rok, $verze) : void {
            unset($values['id']);
            $values['rok'] = $rok;
            $values['verze'] = $verze;
            $this->database->table('rozpocet')->insert($values);
            $this->flashMessage('Nový rozpočet úspěšně založen (zatím bez činností).', 'success');
            $this->redrawControl('flashes');
        };

        $grid->setPagination(false);
        //$grid->setPagination(true);
        //$grid->setItemsPerPageList([10, 30, 100]);

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



