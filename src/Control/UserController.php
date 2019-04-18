<?php

namespace FSWebWorks\SilverStripe\UserInvitations\Control;

use FSWebWorks\SilverStripe\UserInvitations\Model\UserInvitation;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ConfirmedPasswordField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ThemeResourceLoader;

class UserController extends Controller implements PermissionProvider
{

    private static $allowed_actions = [
        'index',
        'accept',
        'success',
        'InvitationForm',
        'AcceptForm',
        'expired',
        'notfound',
    ];

    public function providePermissions()
    {
        return [
            'ACCESS_USER_INVITATIONS' => [
                'name' => _t(
                    'UserController.ACCESS_PERMISSIONS',
                    'Allow user invitations'
                ),
                'category' => _t(
                    'UserController.CMS_ACCESS_CATEGORY',
                    'User Invitations'
                ),
            ],
        ];
    }

    public function init()
    {
        parent::init();

        $action = $this->getRequest()->param('Action');

        if (!Security::getCurrentUser() && ($action === 'index' || $action === 'InvitationForm')) {
            $security = Injector::inst()->get(Security::class);
            $link = $security->Link('login');
            return $this->redirect(Controller::join_links(
                $link,
                "?BackURL={$this->Link('index')}"
            ));
        }
    }

    public function index()
    {
        if (!Permission::check('ACCESS_USER_INVITATIONS')) {
            return Security::permissionFailure();
        } else {
            return $this->renderWithLayout(static::class);
        }
    }

    public function InvitationForm()
    {
        $groups = Member::currentUser()->Groups()->map(
            'Code',
            'Title'
        )->toArray();
        $fields = FieldList::create(
            TextField::create(
                'FirstName',
                _t('UserController.INVITE_FIRSTNAME', 'First name:')
            ),
            EmailField::create(
                'Email',
                _t('UserController.INVITE_EMAIL', 'Invite email:')
            ),
            ListboxField::create(
                'Groups',
                _t('UserController.INVITE_GROUP', 'Add to group'),
                $groups
            )
                ->setRightTitle(_t(
                    'UserController.INVITE_GROUP_RIGHTTITLE',
                    'Ctrl + click to select multiple'
                ))
        );
        $actions = FieldList::create(
            FormAction::create(
                'sendInvite',
                _t('UserController.SEND_INVITATION', 'Send Invitation')
            )
        );
        $requiredFields = RequiredFields::create('FirstName', 'Email');

        if (UserInvitation::config()->get('force_require_group')) {
            $requiredFields->addRequiredField('Groups');
        }

        $form = new Form(
            $this,
            'InvitationForm',
            $fields,
            $actions,
            $requiredFields
        );
        $this->extend('updateInvitationForm', $form);
        return $form;
    }

    /**
     * Records and sends the user's invitation
     * @param $data
     * @param Form $form
     * @return bool|HTTPResponse
     */
    public function sendInvite($data, Form $form)
    {
        if (!Permission::check('ACCESS_USER_INVITATIONS')) {
            $form->sessionMessage(
                _t(
                    'UserController.PERMISSION_FAILURE',
                    "You don't have permission to create user invitations"
                ),
                'bad'
            );
            return $this->redirectBack();
        }
        if (!$form->validationResult()->isValid()) {
            $form->sessionMessage(
                _t(
                    'UserController.SENT_INVITATION_VALIDATION_FAILED',
                    'At least one error occured while trying to save your invite: {error}',
                    ['error' => $form->getValidator()->getErrors()[0]['fieldName']]
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
                ['email' => $data['Email']]
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
        if ($invite = UserInvitation::get()->filter(
            'TempHash',
            $hash
        )->first()) {
            if ($invite->isExpired()) {
                return $this->redirect($this->Link('expired'));
            }
        } else {
            return $this->redirect($this->Link('notfound'));
        }
        return $this->renderWithLayout([
            static::class . '_accept',
            static::class
        ], [
            'Invite' => $invite
        ]);
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
            TextField::create(
                'Surname',
                _t('UserController.ACCEPTFORM_SURNAME', 'Surname:')
            ),
            ConfirmedPasswordField::create('Password'),
            HiddenField::create('HashID')->setValue($hash)
        );
        $actions = FieldList::create(
            FormAction::create(
                'saveInvite',
                _t('UserController.ACCEPTFORM_REGISTER', 'Register')
            )
        );
        $requiredFields = RequiredFields::create('FirstName', 'Surname');
        $form = new Form(
            $this,
            'AcceptForm',
            $fields,
            $actions,
            $requiredFields
        );
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
        if (!$invite = UserInvitation::get()->filter(
            'TempHash',
            $data['HashID']
        )->first()) {
            return $this->notFoundError();
        }
        if ($form->validationResult()->isValid()) {
            $member = Member::create(['Email' => $invite->Email]);
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
        $security = Injector::inst()->get(Security::class);

        return $this->renderWithLayout(
            [
                static::class . '_success',
                static::class,
            ],
            [
                'LoginLink' => $security->Link('login'),
            ]
        );
    }

    public function expired()
    {
        return $this->renderWithLayout([
            static::class . '_expired',
            static::class,
        ]);
    }

    public function notfound()
    {
        return $this->renderWithLayout([
            static::class . '_notfound',
            static::class,
        ]);
    }

    private function forbiddenError()
    {
        return $this->httpError(403, _t(
            'UserController.403_NOTICE',
            'You must be logged in to access this page.'
        ));
    }

    private function notFoundError()
    {
        return $this->redirect($this->Link('notfound'));
    }

    /**
     * Ensure that links for this controller use the customised route.
     * Searches through the rules set up for the class and returns the first route.
     *
     * @param string $action
     * @return string
     */
    public function Link($action = null)
    {
        if ($url = array_search(
            get_called_class(),
            (array)Config::inst()->get(Director::class, 'rules')
        )) {
            // Check for slashes and drop them
            if ($indexOf = stripos($url, '/')) {
                $url = substr($url, 0, $indexOf);
            }
            return $this->join_links($url, $action);
        }
    }

    /**
     * @param array|string $templates
     * @param array|\SilverStripe\View\ArrayData $customFields
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function renderWithLayout($templates, $customFields = [])
    {
        $templates = $this->getLayoutTemplates($templates);
        $mainTemplates = [\Page::class];
        $this->extend('updateMainTemplates', $mainTemplates);

        $viewer = new SSViewer($this->getViewerTemplates());
        $viewer->setTemplateFile(
            'main',
            ThemeResourceLoader::inst()->findTemplate($mainTemplates)
        );
        $viewer->setTemplateFile(
            'Layout',
            ThemeResourceLoader::inst()->findTemplate($templates)
        );

        //print_r($viewer->templates());
        return $viewer->process(
            $this->customise($customFields)
        );
    }

    /**
     * @param array|string $templates
     * @return array
     */
    public function getLayoutTemplates($templates)
    {
        if (is_string($templates)) {
            $templates = [$templates];
        }
        // Always include page template as fallback
        if (count($templates) == 0 || $templates[count($templates) - 1] !== \Page::class) {
            array_push($templates, \Page::class);
        }
        // otherwise it renders funny
        $templates = ['type' => 'Layout'] + $templates;
        return $templates;
    }
}
