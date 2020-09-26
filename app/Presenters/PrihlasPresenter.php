<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use TheNetworg\OAuth2\Client\Provider\Azure;

class PrihlasPresenter extends BasePresenter
{    
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    
    public function renderLogout()
    {
        bdump('yes');
        $this->getUser()->logout();
        $this->getUser()->getIdentity()->jmeno = "nepřihlášený";
        //$this->redirect('Homepage:');
    }

    public function actionLogin()
    {
        $this->doLogin(false);
    }
    
    public function actionLoginAs()
    {
        $this->doLogin(true);
    }
    
    public function doLogin($wantToSelectAccount)
    {
        $provider = new Azure([
            'clientId'          => $this->clientId,
            'clientSecret'      => $this->clientSecret,
            'redirectUri'       => $this->redirectUri,
            'state'             => 'objednavky',
            'scope'             => ['openid', 'profile', 'email', 'user.read']
        ]);
        
        if (!isset($_GET['code'])) {
            // If we don't have an authorization code then get one
            // add prompt for account if user wants it
            $authUrl = $provider->getAuthorizationUrl() . ($wantToSelectAccount ? '&prompt=select_account' : '');
            $_SESSION['oauth2state'] = $provider->getState();
            $this->redirectUrl($authUrl);

        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            exit('Invalid state (is '.$_GET['state'].', should be'.$_SESSION['oauth2state'].', provider->getState() == '.$provider->getState().')');
        } else {
            // Try to get an access token (using the authorization code grant)
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code'],
                'resource' => 'https://graph.windows.net',
            ]);
        
            // Optional: Now you have a token you can look up a users profile data
            try {
                // We got an access token, let's now get the user's details
                $me = $provider->get("me", $token);
                try {
                    $this->getUser()->login($me['userPrincipalName'], $me['mail']);
                } catch (Nette\Security\AuthenticationException $e) {
                    $this->flashMessage($e->getMessage());
                }
                $this->redirect('Homepage:');
            } catch (Exception $e) {
                // Failed to get user details
                exit('Oh dear...');
            }
        }
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

    }

    public function setClientId($clientId) {
        $this->clientId = $clientId;
    }
    public function setClientSecret($clientSecret){
        $this->clientSecret = $clientSecret;
    }
    public function setRedirectUri($redirectUri) {
        $this->redirectUri = $redirectUri;
    }


         
    
}