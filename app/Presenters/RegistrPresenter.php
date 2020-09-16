<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;


class RegistrPresenter extends Nette\Application\UI\Presenter
{
	/** @var Nette\Database\Context */
    private $database;
    
    private Nette\Security\Passwords $mojeGlobalniPromenaPasswords;

	public function __construct(Nette\Database\Context $database, Nette\Security\Passwords $mojeLokalniPromenaPasswords)
	{
        $this->database = $database;
        $this->mojeGlobalniPromenaPasswords = $mojeLokalniPromenaPasswords;
    }
    
    protected function startup()
    {
        parent::startup();

        
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Prihlas:show');

        }
       
        
        
        
        $mojerole = $this->getUser()->getRoles();
     
        if ($mojerole[0] != 3)
        {
            $this->redirect('Homepage:default');
            $this->flashMessage('Nemáte oprávnění.');
        } 
        


    }

    protected function createComponentRegistrationForm(): Form
        {
            $form = new Form;
            $form->addText('name', 'Jméno:');

            $uzivatele = $this->database->table('uzivatel');
          
            foreach ($uzivatele as $polozka) 
            {
             $dohromady = $polozka->jmeno;
             $fetchedNovas[$polozka->id] = $dohromady;
            }


            // $uzivateldatab = $this->database->table('uzivatel')->select('id');
            // $cinnost = $uzivateldatab[$data->cinnostVyber];

          
            $form->addPassword('password', 'Heslo:');
          
            $form->addInteger('mojerole', 'Role:');
           $form->addSelect('id_uzivatel', 'Uživatel:',$fetchedNovas )->setRequired('Vyberte uživatele')->setPrompt(' ');
            // $form->addInteger('id_uzivatel', 'Uživatel:');
           
            $form->addSubmit('send2', 'Registrovat');
            $form->onSuccess[] = [$this, 'formSucceeded'];

            return $form;
        }
    
        public function formSucceeded(Form $form,  $data): void
        {
           

            if ($form['send2']->isSubmittedBy()) {


                 $this->database->table('pokus_jmeno')->insert([
               
                    'jmeno' => $data->name,
                    'heslo' => $this->mojeGlobalniPromenaPasswords->hash($data->password),
                    'mojerole' => $data->mojerole,
                    'id_uzivatel' => $data->id_uzivatel
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