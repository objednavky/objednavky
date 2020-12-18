<?php
//Základ všech ostatních prezenterů


declare(strict_types=1);

namespace App\Presenters;

use App\Model\ObjednavkyManager;
use Nette;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;

abstract class BasePresenter extends Presenter
{
	/** @var Nette\Database\Context */
    protected $database;
    
    /* @var Nette\Http\Session @inject */
    public $session;

    /** @var App\Model\ObjednavkyManager */
    protected $objednavkyManager;

    private $instanceName;
    private $cssClass;
    private $testing;

	public final function __construct(Nette\Database\Context $databaseparam, ObjednavkyManager $objednavkyManager)
	{
		$this->database = $databaseparam;
        $this->objednavkyManager = $objednavkyManager;
	}

    protected function startup()
    {
        parent::startup();
    }

    public final function setInstanceParam($instanceName, $cssClass, $testing)
    {
        $this->instanceName = $instanceName;
        $this->cssClass = $cssClass;
        $this->testing = $testing;
    }

    public final function getInstanceName()
    {
        return $this->instanceName;
    }

    public final function getCssClass()
    {
        return $this->cssClass;
    }

    public final function isTesting()
    {
        return $this->testing;
    }

}



