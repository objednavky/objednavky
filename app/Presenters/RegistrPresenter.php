<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;


class RegistrPresenter extends ObjednavkyBasePresenter
{
    
    protected function startup()
    {
        parent::startup();
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
        $form->addText('prihlaseni', 'Login:');
        $form->addSelect('role', 'Role:', [[1,'jedna'],[2,'dvě'],[3,'tři']])->setRequired('Vyberte roli')->setPrompt(' ');
        $form->addSubmit('send2', 'Registrovat');
        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded(Form $form,  $data): void
    {
        if ($form['send2']->isSubmittedBy()) {
                $this->database->table('uzivatel')->insert([
                'jmeno' => $data->name,
                'prihlaseni' => $data->prihlaseni,
                'role' => $data->mojerole,
            ]);
        }
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