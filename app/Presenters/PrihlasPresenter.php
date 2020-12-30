<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use TheNetworg\OAuth2\Client\Provider\Azure;
use App\MojeServices;

class PrihlasPresenter extends BasePresenter
{    

    private $clientId;
    private $clientSecret;
    private $redirectUri;
    
    public function renderLogout()
    {
        bdump('yes');
        $this->getUser()->logout();
        //$this->getUser()->getIdentity()->jmeno = "nepřihlášený";
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
            'scope'             => ['openid', 'profile', 'email', 'user.read', 'group.readwrite.all']
        ]);
        
        if (empty($this->getHttpRequest()->getQuery('code'))) {
            // If we don't have an authorization code then get one
            // add prompt for account if user wants it
            $authUrl = $provider->getAuthorizationUrl() . ($wantToSelectAccount ? '&prompt=select_account' : '');
            $this->getSession('oauth2')['oauth2state'] = $provider->getState();
            $this->redirectUrl($authUrl);

        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($this->getHttpRequest()->getQuery('state')) || ($this->getHttpRequest()->getQuery('state') !== $this->getSession('oauth2')['oauth2state'])) {
            unset($this->getSession('oauth2')['oauth2state']);
            exit('Invalid state (is '.$this->getHttpRequest()->getQuery('state').', should be '.$this->getSession('oauth2')['oauth2state'].', provider->getState() == '.$provider->getState().')');
        } else {
            // Try to get an access token (using the authorization code grant)
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $this->getHttpRequest()->getQuery('code'),
                'resource' => 'https://graph.windows.net',
            ]);
        
            // Optional: Now you have a token you can look up a users profile data
            try {
                // We got an access token, let's now get the user's details
                $me = $provider->get('me', $token);
                \bdump($me);
                $appRoles = $provider->get('users/'.$me['objectId'].'/appRoleAssignments', $token);   //objectId
                \bdump($appRoles);
/*
                $memberGroups = $provider->post('me/getMemberGroups', ['securityEnabledOnly' => 'false'], $token);   //objectId
                \bdump($memberGroups);
                $allGroups = $provider->get('groups', $token);   //objectId
                \bdump($allGroups);
*/
                $identita = new \App\MojeServices\MojeIdentity(null, $me['objectId'], $me['userPrincipalName'], $me['mail'], $me['displayName'], [], $appRoles);
                try {
                    bdump($this->getUser());
                    bdump($this->getUser()->getAuthenticator());
                    $identita = $this->getUser()->getAuthenticator()->authenticate([$identita, null]);
                    $this->getUser()->login($identita, null);
                } catch (Nette\Security\AuthenticationException $e) {
                    $this->flashMessage($e->getMessage());
                    bdump($e);
                }
                $this->redirect('Homepage:');
            } catch (\Exception $e) {
                // Failed to get user details
                bdump($e);
//                exit('Oh dear...');
                $this->flashMessage($e->getMessage());
                $this->redirect('Homepage:');
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