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
    
    private Nette\Security\Passwords $mojeGlobalniPromenaPasswords;

	public function __construct(Nette\Database\Context $database, Nette\Security\Passwords $mojeLokalniPromenaPasswords)
	{
        $this->database = $database;
        $this->mojeGlobalniPromenaPasswords = $mojeLokalniPromenaPasswords;
    }
    
        public function renderLogout()
        {
            bdump('yes');
            $this->getUser()->logout();
            //$this->redirect('Homepage:');
        }

        protected function createComponentRegistrationForm(): Form
        {
            $form = new Form;
            $form->addText('name', 'Jméno:');
            // $form->addSelect('name', 'Jméno:',["Jarmila","Karla","Tereza"] )->setRequired('Vyberte prosím činnost')->setPrompt(' ');
            $form->addPassword('password', 'Heslo:');
            $form->addSubmit('send', 'Přihlásit');
            $form->addSubmit('send2', 'Registrovat');
            $form->onSuccess[] = [$this, 'formSucceeded'];

            return $form;
        }
    
        public function formSucceeded(Form $form,  $data): void
        {
            if ($form['send']->isSubmittedBy()) {
                try {
                    $this->getUser()->login($data->name, $data->password);
                } catch (Nette\Security\AuthenticationException $e) {
                    $this->flashMessage($e->getMessage());
                }
            
                $this->redirect('Homepage:');
            }

            if ($form['send2']->isSubmittedBy()) {
                 $this->database->table('pokus_jmeno')->insert([
               
                    'jmeno' => $data->name,
                     'heslo' => $this->mojeGlobalniPromenaPasswords->hash($data->password)
                ]);
            }
        }

        


         
            // $this->database->table('pokus_jmeno')->insert([
               
            //         'jmeno' => $data->name,
            //         'heslo' => $data->password
            // ]);

   


	// public function renderShow(int $jedenId): void
	// {
    
    // $jeden = $this->database->table('rozpocet')->get($jedenId);
   
	// if (!$jeden) {
	// 	$this->error('Stránka nebyla nalezena');
	// }


	// $this->template->jeden = $jeden;
    // } 
    
    
    
}