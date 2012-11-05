<?php

namespace VootProxy;

class Provider
{
    // VSCHAR     = %x20-7E
    public $regExpVSCHAR = '/^(?:[\x20-\x7E])*$/';

    private $_provider;

    public function __construct($id, $name, $endpoint)
    {
        $this->_provider = array();
        $this->setId($id);
        $this->setName($name);
        $this->setEndpoint($endpoint);
        $this->setBasicUser(NULL);
        $this->setBasicPass(NULL);
        $this->setFilter(NULL);
        $this->setContactEmail(NULL);
    }

    public static function fromArray(array $a)
    {
        $requiredFields = array ("id", "name", "endpoint");
        foreach ($requiredFields as $r) {
            if (!array_key_exists($r, $a)) {
                throw new ProviderException("not a valid provider, '" . $r . "' not set");
            }
        }
        $c = new static($a['id'], $a['name'], $a['endpoint']);

        if (array_key_exists("basic_user", $a)) {
            $c->setBasicUser($a['basic_user']);
        }
        if (array_key_exists("basic_pass", $a)) {
            $c->setBasicPass($a['basic_pass']);
        }
        if (array_key_exists("filter", $a)) {
            $c->setFilter($a['filter']);
        }
        if (array_key_exists("contact_email", $a)) {
            $c->setContactEmail($a['contact_email']);
        }

        return $c;
    }

    public function setId($i)
    {
        if (empty($i)) {
            throw new ProviderException("id cannot be empty");
        }
        $this->_provider['id'] = $i;
    }

    public function getId()
    {
        return $this->_provider['id'];
    }

    public function setName($n)
    {
        if (empty($n)) {
            throw new ProviderException("name cannot be empty");
        }
        $this->_provider['name'] = $n;
    }

    public function getName()
    {
        return $this->_provider['name'];
    }

    public function setEndpoint($r)
    {
        if (FALSE === filter_var($r, FILTER_VALIDATE_URL)) {
            throw new ProviderException("endpoint should be valid URL");
        }
        // not allowed to have a fragment (#) in it
        if (NULL !== parse_url($r, PHP_URL_FRAGMENT)) {
            throw new ProviderException("endpoint cannot contain a fragment");
        }
        $this->_provider['endpoint'] = $r;
    }

    public function getEndpoint()
    {
        return $this->_provider['endpoint'];
    }

    public function setBasicUser($s)
    {
        $result = preg_match($this->regExpVSCHAR, $s);
        if (1 !== $result) {
            throw new ProviderException("basic_user contains invalid character");
        }
        if (FALSE !== strpos(":", $s)) {
            throw new ProviderException("basic_user contains invalid character");
        }
        $this->_provider['basic_user'] = empty($s) ? NULL : $s;
    }

    public function getBasicUser()
    {
        return $this->_provider['basic_user'];
    }

    public function setBasicPass($s)
    {
        $result = preg_match($this->regExpVSCHAR, $s);
        if (1 !== $result) {
            throw new ProviderException("basic_pass contains invalid character");
        }
        if (FALSE !== strpos(":", $s)) {
            throw new ProviderException("basic_pass contains invalid character");
        }
        $this->_provider['basic_pass'] = empty($s) ? NULL : $s;
    }

    public function getBasicPass()
    {
        return $this->_provider['basic_pass'];
    }

    public function setFilter($f)
    {
        // contains an array of attribute values to match against
        if (NULL !== $f && !is_array($f)) {
            throw new ProviderException("filter should be array");
        }
        $this->_provider['filter'] = $f;
    }

    public function getFilter()
    {
        return $this->_provider['filter'];
    }

    public function setContactEmail($c)
    {
        if (!empty($c)) {
            if (FALSE === filter_var($c, FILTER_VALIDATE_EMAIL)) {
                throw new ProviderException("contact email should be either empty or valid email address");
            }
        }
        $this->_provider['contact_email'] = empty($c) ? NULL : $c;
    }

    public function getContactEmail()
    {
        return $this->_provider['contact_email'];
    }

    public function getProviderAsArray()
    {
        return $this->_provider;
    }

}
