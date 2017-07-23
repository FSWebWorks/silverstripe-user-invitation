<?php

/**
 * Class UserInvitation
 * @package FSWebWorks
 * @subpackage UserInvitation
 *
 * @property string FirstName
 * @property string Email
 * @property string TempHash
 * @property int InvitedByID
 * @property Member InvitedBy
 */
class UserInvitation extends DataObject
{
    private static $db = array(
        'FirstName' => 'Varchar',
        'Email' => 'Varchar(254)',
        'TempHash' => 'Varchar'
    );

    private static $has_one = array(
        'InvitedBy' => 'Member'
    );

    private static $indexes = array(
        'Email' => true,
        'TempHash' => true
    );

    /**
     * Removes the hash field from the list.
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('TempHash');
        return $fields;
    }

    public function onBeforeWrite()
    {
        if (!$this->ID) {
            $generator = new RandomGenerator();
            $this->TempHash = $generator->randomToken('sha1');
            $this->InvitedByID = Member::currentUserID();
        }
        parent::onBeforeWrite();
    }

    /**
     * Sends an invitation to the desired user
     */
    public function sendInvitation()
    {
        return Email::create()
            ->setFrom(Email::config()->get('admin_email'))
            ->setTo($this->Email)
            ->setSubject(
                _t(
                    'UserInvation.EMAIL_SUBJECT',
                    'Invitation from {name}',
                    array('name' => $this->InvitedBy()->FirstName)
                )
            )->setTemplate('UserInvitationEmail')
            ->populateTemplate(
                ArrayData::create(
                    array(
                        'Invite' => $this,
                        'SiteURL' => Director::absoluteBaseURL(),
                    )
                )
            )
            ->send();
    }

    /**
     * Checks if a user invite was already sent, or if a user is already a member
     * @return ValidationResult
     */
    public function validate()
    {
        $valid = parent::validate();

        if (self::get()->filter('Email', $this->Email)->first()) {
            // UserInvitation already sent
            $valid->error(_t('UserInvitation.INVITE_ALREADY_SENT', 'This user was already sent an invite.'));
        }

        if (Member::get()->filter('Email', $this->Email)->first()) {
            // Member already exists
            $valid->error(_t(
                'UserInvitation.MEMBER_ALREADY_EXISTS',
                'This person is already a member of this system.'
            ));
        }
        return $valid;
    }

    /**
     * Checks if this invitation has expired
     * @return bool
     */
    public function isExpired()
    {
        $result = false;
        $days = self::config()->get('days_to_expiry');
        $time = SS_Datetime::now()->Format('U');
        $ago = abs($time - strtotime($this->Created));
        $rounded = round($ago / 86400);
        if ($rounded > $days) {
            $result = true;
        }
        return $result;
    }
}
