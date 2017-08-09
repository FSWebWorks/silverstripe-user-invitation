<?php

class UserController extends Controller
{

    private static $allowed_actions = array(
        'index',
        'accept',
        'success',
        'InvitationForm',
        'AcceptForm',
        'expired',
        'notfound'
    );

    public function index()
    {
        if (!Member::currentUserID()) {
            return $this->redirect('/Security/login');
        }
        return $this->renderWith(array('UserController', 'Page'));
    }

    public function InvitationForm()
    {
        $groups = Member::currentUser()->Groups()->map('Code', 'Title')->toArray();
        $fields = FieldList::create(
            TextField::create('FirstName', _t('UserController.INVITE_FIRSTNAME', 'First name:')),
            EmailField::create('Email', _t('UserController.INVITE_EMAIL', 'Invite email:')),
            ListboxField::create('Groups', _t('UserController.INVITE_GROUP', 'Add to group'), $groups)
                ->setMultiple(true)
                ->setRightTitle(_t('UserController.INVITE_GROUP_RIGHTTITLE', 'Ctrl + click to select multiple'))
        );
        $actions = FieldList::create(
            FormAction::create('sendInvite', _t('UserController.SEND_INVITATION', 'Send Invitation'))
        );
        $requiredFields = RequiredFields::create('FirstName', 'Email');

        if (UserInvitation::config()->get('force_require_group')) {
            $requiredFields->addRequiredField('Groups');
        }

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
        if (!$form->validate()) {
            $form->sessionMessage(
                _t(
                    'UserController.SENT_INVITATION_VALIDATION_FAILED',
                    'At least one error occured while trying to save your invite: {error}',
                    array('error' => $form->getValidator()->getErrors()[0]['fieldName'])
                ),
                'bad'
            );
            return $this->redirectBack();
        }

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

        $form->sessionMessage(
            _t(
                'UserController.SENT_INVITATION',
                'An invitation was sent to {email}.',
                array('email' => $data['Email'])
            ),
            'good'
        );
        return $this->redirectBack();
    }


    public function accept()
    {
        if (!$hash = $this->getRequest()->param('ID')) {
            return $this->forbiddenError();
        }
        if ($invite = UserInvitation::get()->filter('TempHash', $hash)->first()) {
            if ($invite->isExpired()) {
                return $this->redirect($this->Link('expired'));
            }
        } else {
            return $this->redirect($this->Link('notfound'));
        }
        return $this->renderWith(array('UserController_accept', 'Page'), array('Invite' => $invite));
    }

    public function AcceptForm()
    {
        $hash = $this->getRequest()->param('ID');
        $invite = UserInvitation::get()->filter('TempHash', $hash)->first();
        $firstName = ($invite) ? $invite->FirstName : '';

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
        if (!$invite = UserInvitation::get()->filter('TempHash', $data['HashID'])->first()) {
            return $this->notFoundError();
        }
        if ($form->validate()) {
            $member = Member::create(array('Email' => $invite->Email));
            $form->saveInto($member);
            try {
                if ($member->validate()) {
                    $member->write();
                    // Add user group info
                    $groups = explode(',', $invite->Groups);
                    foreach ($groups as $groupCode) {
                        $member->addToGroupByCode($groupCode);
                    }
                }
            } catch (ValidationException $e) {
                $form->sessionMessage(
                    $e->getMessage(),
                    'bad'
                );
                return $this->redirectBack();
            }
            // Delete invitation
            $invite->delete();
            return $this->redirect($this->Link('success'));
        } else {
            $form->sessionMessage(
                Convert::array2json($form->getValidator()->getErrors()),
                'bad'
            );
            return $this->redirectBack();
        }
    }

    public function success()
    {
        return $this->renderWith(
            array('UserController_success', 'Page'),
            array('BaseURL' => Director::absoluteBaseURL())
        );
    }

    public function expired()
    {
        return $this->renderWith(array('UserController_expired', 'Page'));
    }

    public function notfound()
    {
        return $this->renderWith(array('UserController_notfound', 'Page'));
    }

    private function forbiddenError()
    {
        return $this->httpError(403, _t('UserController.403_NOTICE', 'You must be logged in to access this page.'));
    }

    private function notFoundError()
    {
        return $this->redirect($this->Link('notfound'));
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
}
