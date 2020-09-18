<?php
//Základ všech ostatních prezenterů


declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;

abstract class ObjednavkyBasePresenter extends Presenter
{
	/** @var Nette\Database\Context */
	protected $database;

	public final function __construct(Nette\Database\Context $databaseparam)
	{
		$this->database = $databaseparam;
	}

    protected function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Prihlas:show');
        }
    }

    public final function getSetup($id)
    {
         return $this->database->table('setup')->where('id',$id)->fetch();
    }

    public final function prihlasenyId()
    {
       
        return $this->getUser()->getIdentity()->id; 
    }

    protected final function sumColumn($array ,$columnArgument)
    {
        $sum = 0;
        foreach ($array as $item) {
            $sum += $item->$columnArgument;
        }
        return $sum;
    }
}



