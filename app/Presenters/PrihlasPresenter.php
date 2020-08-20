<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;


class PrihlasPresenter extends Nette\Application\UI\Presenter
{
	/** @var Nette\Database\Context */
	private $database;

	public function __construct(Nette\Database\Context $database)
	{
        $this->database = $database;
       
	}

    protected function createComponentRegistrationForm(): Form
        {
            $form = new Form;
            $form->addText('name', 'Jméno:');
            $form->addPassword('password', 'Heslo:');
            $form->addSubmit('send', 'Registrovat');
            $form->onSuccess[] = [$this, 'formSucceeded'];
            return $form;
        }
    
        public function formSucceeded(Form $form, $data): void
        {
            // tady zpracujeme data odeslaná formulářem
            // $data->name obsahuje jméno
            // $data->password obsahuje heslo
            $this->flashMessage('Byl jste úspěšně registrován.');
            $this->redirect('Homepage:');
        }



    


	// public function renderShow(int $jedenId): void
	// {
    
    // $jeden = $this->database->table('rozpocet')->get($jedenId);
   
	// if (!$jeden) {
	// 	$this->error('Stránka nebyla nalezena');
	// }


	// $this->template->jeden = $jeden;
    // } 
    
    
    
}