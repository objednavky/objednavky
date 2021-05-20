<?php
//Editace činností k detailnímu rozpočtu


declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Utils;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use Ublaboo\DataGrid\Column\Action\Confirmation\StringConfirmation;
use App\Model\ObjednavkyManager;

class EditCinnostiPresenter extends ObjednavkyBasePresenter
{

    private $sessionSection;

    protected function startup()
    {
        parent::startup();
        if (!isset($this->sessionSection)) {
            $this->sessionSection = $this->getSession('EditCinnostiPresenter');
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

    public function createComponentEditCinnostiGrid($name) {
        bdump('createComponentEditCinnostiGrid');
        $grid = new DataGrid($this, $name);
        $rok = $this->sessionSection->rok;
        $verze = $this->sessionSection->verze;
        $rozpocty = $this->database->table('rozpocet')->where('rozpocet.rok', $rok)->where('rozpocet.verze',$verze)->fetchPairs('id','id');
        $source = $this->database->table('cinnost')
            ->select('cinnost.id AS id, cinnost.cinnost AS cinnost, nazev_cinnosti, id_rozpocet, vyber, COUNT(:objednavky.id) AS pocet_objednavek')
            ->where('id_rozpocet',$rozpocty)
            ->group('cinnost.id, cinnost.cinnost, nazev_cinnosti, id_rozpocet, vyber')
            ->order('cinnost.cinnost ASC');
        //$this->sessionSection->source = $source;
        //$grid->setPrimaryKey('cinnost.id');
        $grid->setDataSource($source);
        bdump($grid);
        bdump($source);


        $grid->addColumnNumber('id', 'Id')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('cinnost', 'Činnost')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('nazev_cinnosti', 'Název')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('rozpocet.rozpocet', 'Rozpočet')->setSortable()->setSortableResetPagination();
        $grid->addColumnNumber('vyber', 'Nabízet uživatelům?')->setSortable()->setSortableResetPagination();
        $grid->addColumnNumber('pocet_objednavek', 'Počet objednávek')->setSortable()->setSortableResetPagination();
        $grid->addExportCsv('Export do csv', 'rozpocet.csv', 'Windows-1250')->setTitle('Export do csv');

        $grid->addInlineEdit()
        ->onControlAdd[] = function(Nette\Forms\Container $container) use ($rok, $verze): void {
            $container->addText('cinnost', '')->setRequired('Uveďte zkratku činnosti.');
            $container->addText('nazev_cinnosti', '')->setRequired('Uveďte název činnosti.');
            $container->addSelect('id_rozpocet','Rozpočet:',$this->database->table('rozpocet')->select('rozpocet.id, rozpocet')->where('rok', $rok)->where('verze',$verze)->order('rozpocet')->fetchPairs('id','rozpocet'))->setRequired('Přidělte činnosti rozpočet.');
            $container->addCheckbox('vyber', '');
            $container->addHidden('cinnost_old','');
        };
    
        $grid->getInlineEdit()->onSetDefaults[] = function(Nette\Forms\Container $container, $item): void {
            bdump($item);
            $container->setDefaults([
                'cinnost' => $item->cinnost,
                'nazev_cinnosti' => $item->nazev_cinnosti,
                'id_rozpocet' => $item->id_rozpocet,
                'vyber' => $item->vyber,
                'cinnost_old' => $item->cinnost,
            ]);
        };
        
        $grid->getInlineEdit()->onSubmit[] = function($id, Nette\Utils\ArrayHash $values) use ($rok): void {
            if ($id) {
                $values['vyber'] = ($values['vyber'] ? 1 : 0);
                try {
                    // není činnost s touto zkratkou v daném roce už založena?
                    if ($this->database->table('cinnost')->where('cinnost', $values['cinnost'])->where('rok',$rok)->where('NOT id = ?', $id)->count('id') == 0) {
                        $this->database->table('cinnost')->where('id',$id)->update($values);
                        $this->flashMessage('Činnost byla úspěšně změněna.', 'success');
                    } else {
                        $this->flashMessage('Jiná činnost se zkratkou ' . $values['cinnost'] . ' v roce ' . $rok . ' už existuje, nebyla uložena ', 'danger');
                    };
                } catch (Exception $e) {
                    $this->flashMessage('Novou činnost se zřejmě nepodařilo uložit - chyba '. $e, 'danger');
                }
            }
        };
        $grid->getInlineEdit()->setShowNonEditingColumns();
        
        $grid->addInlineAdd()
        ->onControlAdd[] = function(Nette\Forms\Container $container) use ($rok, $verze) : void {
            $container->addText('cinnost', '')->setRequired('Uveďte zkratku činnosti.')->addRule([$this, 'jeCinnostJedinecna'], 'jedinecnaCinnost', $rok);
            $container->addText('nazev_cinnosti', '')->setRequired('Uveďte název činnosti.');
            $container->addSelect('id_rozpocet','Rozpočet:',$this->database->table('rozpocet')->select('id, rozpocet')->where('rok', $rok)->where('verze',$verze)->order('rozpocet')->fetchPairs('id','rozpocet'))->setRequired('Přidělte činnosti rozpočet.');
            $container->addCheckbox('vyber', '');
        };

        $grid->getInlineAdd()->onSubmit[] = function(Nette\Utils\ArrayHash $values) use ($rok, $verze) : void {
            unset($values['id']);
            $values['vyber'] = ($values['vyber'] ? 1 : 0);
            $values['rok'] = $rok;
            try {
                // není činnost s touto zkratkou v daném roce už založena?
                if ($this->database->table('cinnost')->where('cinnost', $values['cinnost'])->where('rok',$rok)->count('id') == 0) {
                    $this->database->table('cinnost')->insert($values);
                    $this->flashMessage('Nová činnost úspěšně založena.', 'success');
                } else {
                    $this->flashMessage('Činnost ' . $values['cinnost'] . ' v roce ' . $rok . ' už existuje, nebyla uložena ', 'danger');
                };
            } catch (Exception $e) {
                $this->flashMessage('Novou činnost se zřejmě nepodařilo založit - chyba '. $e, 'danger');
            }
            
            $this->redrawControl('flashes');
            
        };

        $grid->addAction('delete', '', 'deleteCinnost!')
            ->setClass(function($item) { return 'btn btn-xs ajax btn-secondary' . ($item->pocet_objednavek > 0 ? ' disabled' : ''); })
            ->setIcon('trash')
            ->setTitle('Smazat činnost')
            ->setConfirmation(
                new StringConfirmation('Opravdu chcete smazat činnost %s?', 'cinnost') // Second parameter is optional
            );


        //$grid->setPagination(false);
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

    /**
     * kontrola při vkládání nové činnosti, zda je v daném roce jedinečná
     */
    public function jeCinnostJedinecna($control, $rok) {
        return ($this->database->table('cinnost')->where('cinnost', $control->value)->where('rok',$rok)->count('id') == 0);
    }


    public function handleDeleteCinnost($id)
    {
        try {
            // nenexistuje pro mazanou cinnost objednavka?
            $cinnost = $this->database->table('cinnost')->get($id);
            if ($this->database->table('objednavky')->where('cinnost', $id)->count('id') == 0) {
                $this->database->table('cinnost')->where('id = ?', $id)->delete();
                $this->flashMessage('Činnost ' . $cinnost->cinnost . ' byla smazána.', 'success');
            } else {
                $this->flashMessage('Pro činnost ' . $cinnost->cinnost . ' existují objednávky, nemůže být smazána.', 'danger');
            };
        } catch (Exception $e) {
            $this->flashMessage('Činnost se zřejmě nepodařilo smazat - chyba '. $e, 'danger');
        }

        if ($this->isAjax()) {
            $this->redrawControl('flashes');
            $this['editCinnostiGrid']->reload();
        } else {
            $this->redirect('this');
        }
    }

}



