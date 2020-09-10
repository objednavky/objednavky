<?php

/**
 * Nette Forms & Bootstap v4 rendering example.
 */

namespace App\Presenters;



use Nette\Forms\Form;
use Tracy\Debugger;
use Tracy\Dumper;

class MujPomocnik
{
    static function getSetupGlobal($database, $id)
    {
        return $database->table('setup')->where('id',$id)->fetch();
    }


    

}