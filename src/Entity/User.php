<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Entity;

/**
 * User Entity (Moodle Native).
 *
 * @api
 */
class User extends AbstractMoodleEntity
{
    protected string $auth = 'manual';

    protected int $confirmed = 0;

    protected int $policyagreed = 0;

    protected int $deleted = 0;

    protected int $suspended = 0;

    protected int $mnethostid = 0;

    protected string $username = '';

    protected string $password = '';

    protected string $idnumber = '';

    protected string $firstname = '';

    protected string $lastname = '';

    protected string $email = '';

    protected int $emailstop = 0;

    protected string $phone1 = '';

    protected string $phone2 = '';

    protected string $institution = '';

    protected string $department = '';

    protected string $address = '';

    protected string $city = '';

    protected string $country = '';

    protected string $lang = 'en';

    protected string $calendartype = 'gregorian';

    protected string $theme = '';

    protected string $timezone = '99';

    protected int $firstaccess = 0;

    protected int $lastaccess = 0;

    protected int $lastlogin = 0;

    protected int $currentlogin = 0;

    protected string $lastip = '';

    protected string $secret = '';

    protected int $picture = 0;

    protected ?string $description = null;

    protected int $descriptionformat = 1;

    protected int $mailformat = 1;

    protected int $maildigest = 0;

    protected int $maildisplay = 2;

    protected int $autosubscribe = 1;

    protected int $trackforums = 0;

    protected int $trustbitmask = 0;

    protected ?string $imagealt = null;

    protected ?string $lastnamephonetic = null;

    protected ?string $firstnamephonetic = null;

    protected ?string $middlename = null;

    protected ?string $alternatename = null;

    protected ?string $moodlenetprofile = null;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'user';
    }

    /**
     * Helper to get fullname.
     *
     * @return string
     */
    public function getFullname(): string
    {
        return trim($this->firstname . ' ' . $this->lastname);
    }

    /**
     * Get authentication plugin.
     *
     * @return string
     */
    public function getAuth(): string
    {
        return $this->auth;
    }

    /**
     * Set authentication plugin.
     *
     * @param string $auth
     *
     * @return $this
     */
    public function withAuth(string $auth): self
    {
        $this->auth = $auth;

        return $this;
    }

    /**
     * Get confirmation flag.
     *
     * @return int
     */
    public function getConfirmed(): int
    {
        return $this->confirmed;
    }

    /**
     * Set confirmation flag.
     *
     * @param int $confirmed
     *
     * @return $this
     */
    public function withConfirmed(int $confirmed): self
    {
        $this->confirmed = $confirmed;

        return $this;
    }

    /**
     * Get policy agreement flag.
     *
     * @return int
     */
    public function getPolicyagreed(): int
    {
        return $this->policyagreed;
    }

    /**
     * Set policy agreement flag.
     *
     * @param int $policyagreed
     *
     * @return $this
     */
    public function withPolicyagreed(int $policyagreed): self
    {
        $this->policyagreed = $policyagreed;

        return $this;
    }

    /**
     * Get deleted flag.
     *
     * @return int
     */
    public function getDeleted(): int
    {
        return $this->deleted;
    }

    /**
     * Set deleted flag.
     *
     * @param int $deleted
     *
     * @return $this
     */
    public function withDeleted(int $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get suspended flag.
     *
     * @return int
     */
    public function getSuspended(): int
    {
        return $this->suspended;
    }

    /**
     * Set suspended flag.
     *
     * @param int $suspended
     *
     * @return $this
     */
    public function withSuspended(int $suspended): self
    {
        $this->suspended = $suspended;

        return $this;
    }

    /**
     * Get MNet host identifier.
     *
     * @return int
     */
    public function getMnethostid(): int
    {
        return $this->mnethostid;
    }

    /**
     * Set MNet host identifier.
     *
     * @param int $mnethostid
     *
     * @return $this
     */
    public function withMnethostid(int $mnethostid): self
    {
        $this->mnethostid = $mnethostid;

        return $this;
    }

    /**
     * Get username.
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Set username.
     *
     * @param string $username
     *
     * @return $this
     */
    public function withUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get hashed password.
     *
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Set hashed password.
     *
     * @param string $password
     *
     * @return $this
     */
    public function withPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get idnumber.
     *
     * @return string
     */
    public function getIdnumber(): string
    {
        return $this->idnumber;
    }

    /**
     * Set idnumber.
     *
     * @param string $idnumber
     *
     * @return $this
     */
    public function withIdnumber(string $idnumber): self
    {
        $this->idnumber = $idnumber;

        return $this;
    }

    /**
     * Get first name.
     *
     * @return string
     */
    public function getFirstname(): string
    {
        return $this->firstname;
    }

    /**
     * Set first name.
     *
     * @param string $firstname
     *
     * @return $this
     */
    public function withFirstname(string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Get last name.
     *
     * @return string
     */
    public function getLastname(): string
    {
        return $this->lastname;
    }

    /**
     * Set last name.
     *
     * @param string $lastname
     *
     * @return $this
     */
    public function withLastname(string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get email.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set email.
     *
     * @param string $email
     *
     * @return $this
     */
    public function withEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email stop flag.
     *
     * @return int
     */
    public function getEmailstop(): int
    {
        return $this->emailstop;
    }

    /**
     * Set email stop flag.
     *
     * @param int $emailstop
     *
     * @return $this
     */
    public function withEmailstop(int $emailstop): self
    {
        $this->emailstop = $emailstop;

        return $this;
    }

    /**
     * Get primary phone.
     *
     * @return string
     */
    public function getPhone1(): string
    {
        return $this->phone1;
    }

    /**
     * Set primary phone.
     *
     * @param string $phone1
     *
     * @return $this
     */
    public function withPhone1(string $phone1): self
    {
        $this->phone1 = $phone1;

        return $this;
    }

    /**
     * Get secondary phone.
     *
     * @return string
     */
    public function getPhone2(): string
    {
        return $this->phone2;
    }

    /**
     * Set secondary phone.
     *
     * @param string $phone2
     *
     * @return $this
     */
    public function withPhone2(string $phone2): self
    {
        $this->phone2 = $phone2;

        return $this;
    }

    /**
     * Get institution.
     *
     * @return string
     */
    public function getInstitution(): string
    {
        return $this->institution;
    }

    /**
     * Set institution.
     *
     * @param string $institution
     *
     * @return $this
     */
    public function withInstitution(string $institution): self
    {
        $this->institution = $institution;

        return $this;
    }

    /**
     * Get department.
     *
     * @return string
     */
    public function getDepartment(): string
    {
        return $this->department;
    }

    /**
     * Set department.
     *
     * @param string $department
     *
     * @return $this
     */
    public function withDepartment(string $department): self
    {
        $this->department = $department;

        return $this;
    }

    /**
     * Get address.
     *
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * Set address.
     *
     * @param string $address
     *
     * @return $this
     */
    public function withAddress(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get city.
     *
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * Set city.
     *
     * @param string $city
     *
     * @return $this
     */
    public function withCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get country code.
     *
     * @return string
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * Set country code.
     *
     * @param string $country
     *
     * @return $this
     */
    public function withCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get language.
     *
     * @return string
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * Set language.
     *
     * @param string $lang
     *
     * @return $this
     */
    public function withLang(string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    /**
     * Get calendar type.
     *
     * @return string
     */
    public function getCalendartype(): string
    {
        return $this->calendartype;
    }

    /**
     * Set calendar type.
     *
     * @param string $calendartype
     *
     * @return $this
     */
    public function withCalendartype(string $calendartype): self
    {
        $this->calendartype = $calendartype;

        return $this;
    }

    /**
     * Get theme.
     *
     * @return string
     */
    public function getTheme(): string
    {
        return $this->theme;
    }

    /**
     * Set theme.
     *
     * @param string $theme
     *
     * @return $this
     */
    public function withTheme(string $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    /**
     * Get timezone.
     *
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * Set timezone.
     *
     * @param string $timezone
     *
     * @return $this
     */
    public function withTimezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Get first access timestamp.
     *
     * @return int
     */
    public function getFirstaccess(): int
    {
        return $this->firstaccess;
    }

    /**
     * Set first access timestamp.
     *
     * @param int $firstaccess
     *
     * @return $this
     */
    public function withFirstaccess(int $firstaccess): self
    {
        $this->firstaccess = $firstaccess;

        return $this;
    }

    /**
     * Get last access timestamp.
     *
     * @return int
     */
    public function getLastaccess(): int
    {
        return $this->lastaccess;
    }

    /**
     * Set last access timestamp.
     *
     * @param int $lastaccess
     *
     * @return $this
     */
    public function withLastaccess(int $lastaccess): self
    {
        $this->lastaccess = $lastaccess;

        return $this;
    }

    /**
     * Get last login timestamp.
     *
     * @return int
     */
    public function getLastlogin(): int
    {
        return $this->lastlogin;
    }

    /**
     * Set last login timestamp.
     *
     * @param int $lastlogin
     *
     * @return $this
     */
    public function withLastlogin(int $lastlogin): self
    {
        $this->lastlogin = $lastlogin;

        return $this;
    }

    /**
     * Get current login timestamp.
     *
     * @return int
     */
    public function getCurrentlogin(): int
    {
        return $this->currentlogin;
    }

    /**
     * Set current login timestamp.
     *
     * @param int $currentlogin
     *
     * @return $this
     */
    public function withCurrentlogin(int $currentlogin): self
    {
        $this->currentlogin = $currentlogin;

        return $this;
    }

    /**
     * Get last IP.
     *
     * @return string
     */
    public function getLastip(): string
    {
        return $this->lastip;
    }

    /**
     * Set last IP.
     *
     * @param string $lastip
     *
     * @return $this
     */
    public function withLastip(string $lastip): self
    {
        $this->lastip = $lastip;

        return $this;
    }

    /**
     * Get secret string.
     *
     * @return string
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * Set secret string.
     *
     * @param string $secret
     *
     * @return $this
     */
    public function withSecret(string $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * Get picture revision.
     *
     * @return int
     */
    public function getPicture(): int
    {
        return $this->picture;
    }

    /**
     * Set picture revision.
     *
     * @param int $picture
     *
     * @return $this
     */
    public function withPicture(int $picture): self
    {
        $this->picture = $picture;

        return $this;
    }

    /**
     * Get description.
     *
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set description.
     *
     * @param null|string $description
     *
     * @return $this
     */
    public function withDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description format.
     *
     * @return int
     */
    public function getDescriptionformat(): int
    {
        return $this->descriptionformat;
    }

    /**
     * Set description format.
     *
     * @param int $descriptionformat
     *
     * @return $this
     */
    public function withDescriptionformat(int $descriptionformat): self
    {
        $this->descriptionformat = $descriptionformat;

        return $this;
    }

    /**
     * Get mail format.
     *
     * @return int
     */
    public function getMailformat(): int
    {
        return $this->mailformat;
    }

    /**
     * Set mail format.
     *
     * @param int $mailformat
     *
     * @return $this
     */
    public function withMailformat(int $mailformat): self
    {
        $this->mailformat = $mailformat;

        return $this;
    }

    /**
     * Get mail digest mode.
     *
     * @return int
     */
    public function getMaildigest(): int
    {
        return $this->maildigest;
    }

    /**
     * Set mail digest mode.
     *
     * @param int $maildigest
     *
     * @return $this
     */
    public function withMaildigest(int $maildigest): self
    {
        $this->maildigest = $maildigest;

        return $this;
    }

    /**
     * Get mail display preference.
     *
     * @return int
     */
    public function getMaildisplay(): int
    {
        return $this->maildisplay;
    }

    /**
     * Set mail display preference.
     *
     * @param int $maildisplay
     *
     * @return $this
     */
    public function withMaildisplay(int $maildisplay): self
    {
        $this->maildisplay = $maildisplay;

        return $this;
    }

    /**
     * Get autosubscribe preference.
     *
     * @return int
     */
    public function getAutosubscribe(): int
    {
        return $this->autosubscribe;
    }

    /**
     * Set autosubscribe preference.
     *
     * @param int $autosubscribe
     *
     * @return $this
     */
    public function withAutosubscribe(int $autosubscribe): self
    {
        $this->autosubscribe = $autosubscribe;

        return $this;
    }

    /**
     * Get forums tracking preference.
     *
     * @return int
     */
    public function getTrackforums(): int
    {
        return $this->trackforums;
    }

    /**
     * Set forums tracking preference.
     *
     * @param int $trackforums
     *
     * @return $this
     */
    public function withTrackforums(int $trackforums): self
    {
        $this->trackforums = $trackforums;

        return $this;
    }

    /**
     * Get trust bitmask.
     *
     * @return int
     */
    public function getTrustbitmask(): int
    {
        return $this->trustbitmask;
    }

    /**
     * Set trust bitmask.
     *
     * @param int $trustbitmask
     *
     * @return $this
     */
    public function withTrustbitmask(int $trustbitmask): self
    {
        $this->trustbitmask = $trustbitmask;

        return $this;
    }

    /**
     * Get image alt text.
     *
     * @return null|string
     */
    public function getImagealt(): ?string
    {
        return $this->imagealt;
    }

    /**
     * Set image alt text.
     *
     * @param null|string $imagealt
     *
     * @return $this
     */
    public function withImagealt(?string $imagealt): self
    {
        $this->imagealt = $imagealt;

        return $this;
    }

    /**
     * Get lastname phonetic.
     *
     * @return null|string
     */
    public function getLastnamephonetic(): ?string
    {
        return $this->lastnamephonetic;
    }

    /**
     * Set lastname phonetic.
     *
     * @param null|string $lastnamephonetic
     *
     * @return $this
     */
    public function withLastnamephonetic(?string $lastnamephonetic): self
    {
        $this->lastnamephonetic = $lastnamephonetic;

        return $this;
    }

    /**
     * Get firstname phonetic.
     *
     * @return null|string
     */
    public function getFirstnamephonetic(): ?string
    {
        return $this->firstnamephonetic;
    }

    /**
     * Set firstname phonetic.
     *
     * @param null|string $firstnamephonetic
     *
     * @return $this
     */
    public function withFirstnamephonetic(?string $firstnamephonetic): self
    {
        $this->firstnamephonetic = $firstnamephonetic;

        return $this;
    }

    /**
     * Get middle name.
     *
     * @return null|string
     */
    public function getMiddlename(): ?string
    {
        return $this->middlename;
    }

    /**
     * Set middle name.
     *
     * @param null|string $middlename
     *
     * @return $this
     */
    public function withMiddlename(?string $middlename): self
    {
        $this->middlename = $middlename;

        return $this;
    }

    /**
     * Get alternate name.
     *
     * @return null|string
     */
    public function getAlternatename(): ?string
    {
        return $this->alternatename;
    }

    /**
     * Set alternate name.
     *
     * @param null|string $alternatename
     *
     * @return $this
     */
    public function withAlternatename(?string $alternatename): self
    {
        $this->alternatename = $alternatename;

        return $this;
    }

    /**
     * Get MoodleNet profile.
     *
     * @return null|string
     */
    public function getMoodlenetprofile(): ?string
    {
        return $this->moodlenetprofile;
    }

    /**
     * Set MoodleNet profile.
     *
     * @param null|string $moodlenetprofile
     *
     * @return $this
     */
    public function withMoodlenetprofile(?string $moodlenetprofile): self
    {
        $this->moodlenetprofile = $moodlenetprofile;

        return $this;
    }
}
