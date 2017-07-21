<?php

class UserController extends Page_Controller
{
    private $accepted = false;

    private static $allowed_actions = array(
        'index',
        'accept',
        'InvitationForm',
        'AcceptForm'
    );

    public function index()
    {
        if (!Member::currentUser()) {
            return $this->forbiddenError();
        }
        return $this->renderWith(array('UserController', 'Page'));
    }

    public function InvitationForm()
    {
        $fields = FieldList::create(
            TextField::create('FirstName', _t('UserController.INVITE_FIRSTNAME', 'First name:')),
            EmailField::create('Email', _t('UserController.INVITE_EMAIL', 'Invite email:'))
        );
        $actions = FieldList::create(
            FormAction::create('sendInvite', _t('UserController.SEND_INVITATION', 'Send Invitation'))
        );
        $requiredFields = RequiredFields::create('FirstName', 'Email');
        $form = new Form($this, 'InvitationForm', $fields, $actions, $requiredFields);
        $this->extend('updateInvitationForm', $form);
        return $form;
    }

    /**
     * Records and sends the user's invitation
     * @param $data
     * @param Form $form
     * @return bool|SS_HTTPResponse
     */
    public function sendInvite($data, Form $form)
    {
        if ($form->validate()) {
            $invite = UserInvitation::create();
            $form->saveInto($invite);
            try {
                $invite->write();
            } catch (ValidationException $e) {
                $form->sessionMessage(
                    $e->getMessage(),
                    'bad'
                );
                return $this->redirectBack();
            }
            $invite->sendInvitation();
        }

        $form->sessionMessage(
            _t('UserController.SENT_INVITATION', 'An invitation was sent to {email}.', array('email' => $data['Email'])),
            'good'
        );
        return $this->redirectBack();
    }


    public function accept()
    {
        if (!$hash = $this->getRequest()->param('ID')) {
            return $this->forbiddenError();
        }
        return $this->renderWith(array('UserController_accept', 'Page'));
    }

    public function AcceptForm()
    {
        $hash = $this->getRequest()->param('ID');
        $firstName = ($invite = UserInvitation::get()->filter('TempHash', $hash)->first()) ? $invite->FirstName : '';

        $fields = FieldList::create(
            TextField::create(
                'FirstName',
                _t('UserController.ACCEPTFORM_FIRSTNAME', 'First name:'),
                $firstName
            ),
            TextField::create('Surname', _t('UserController.ACCEPTFORM_SURNAME', 'Surname:')),
            ConfirmedPasswordField::create('Password'),
            HiddenField::create('HashID')->setValue($hash)
        );
        $actions = FieldList::create(
            FormAction::create('saveInvite', _t('UserController.ACCEPTFORM_REGISTER', 'Register'))
        );
        $requiredFields = RequiredFields::create('FirstName', 'Surname');
        $form = new  Form($this, 'AcceptForm', $fields, $actions, $requiredFields);
        Session::set('UserInvitation.accepted', true);
        $this->extend('updateAcceptForm', $form);
        return $form;
    }

    /**
     * @param $data
     * @param Form $form
     * @return bool|SS_HTTPResponse
     */
    public function saveInvite($data, Form $form)
    {
        if ($form->validate()) {
            if (!$invite = UserInvitation::get()->filter('TempHash', $data['HashID'])->first()) {
                return $this->notFoundError();
            }
            $member = Member::create();
            $member->Email = $invite->Email;
            $form->saveInto($member);
            try {
                if ($member->validate()) {
                    $member->write();
                }
            } catch (ValidationException $e) {
                $form->sessionMessage(
                    $e->getMessage(),
                    'bad'
                );
                return $this->redirectBack();
            }
            // Delete invite record
            if ($invite = UserInvitation::get()->filter('Email', $member->Email)->first()) {
                $invite->delete();
            }
            $baseURL = Director::absoluteBaseURL();
            $form->sessionMessage(
                _t(
                    'UserController.ACCEPT_SUCCESS',
                    'Congragulations! You are now registered member. Visit {site} to log in.',
                    array('site' => "<a href=\"{$baseURL}\">{$baseURL}</a>")
                ),
                'good'
            );
        }

        return $this->redirectBack();
    }

    private function forbiddenError()
    {
        return $this->httpError(403, _t('UserController.403_NOTICE', 'You must be logged in to access this page.'));
    }

    private function notFoundError()
    {
        return $this->httpError(403, _t('UserController.404_NOTICE', 'Oops, the invitation ID was not found.'));
    }

    /**
     * Ensure that links for this controller use the customised route.
     * Searches through the rules set up for the class and returns the first route.
     *
     * @param  string $action
     * @return string
     */
    public function Link($action = null)
    {
        if ($url = array_search(get_called_class(), (array)Config::inst()->get('Director', 'rules'))) {
            // Check for slashes and drop them
            if ($indexOf = stripos($url, '/')) {
                $url = substr($url, 0, $indexOf);
            }
            return $this->join_links($url, $action);
        }
    }

    public function getAccepted()
    {
        return Session::get('UserInvitation.accepted');
    }

}
