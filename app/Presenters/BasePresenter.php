<?php
//Základ všech ostatních prezenterů


declare(strict_types=1);

namespace App\Presenters;

use App\Model\ObjednavkyManager;
use App\MojeServices\ParovaniDenikuService;
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
    
    /** @var App\Model\ObjednavkyManager */
    protected $objednavkyManager;

    /** @var App\MojeServices\ParovaniDenikuService */
    protected $parovaniDenikuService;

    private $instanceName;
    private $cssClass;
    private $testing;

	public final function __construct(Nette\Database\Context $databaseparam, ObjednavkyManager $objednavkyManager, ParovaniDenikuService $parovaniDenikuService)
	{
		$this->database = $databaseparam;
        $this->objednavkyManager = $objednavkyManager;
        $this->parovaniDenikuService = $parovaniDenikuService;
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



