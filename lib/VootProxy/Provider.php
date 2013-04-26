<?php

namespace VootProxy;

class Provider
{
    // VSCHAR     = %x20-7E
    const REGEXP_VSCHAR = '/^(?:[\x20-\x7E])*$/';

    private $_data;

    public function __construct($id, $endpoint)
    {
        $this->_data = array();
        $this->setId($id);
        $this->setEndpoint($endpoint);
    }

    public static function fromArray(array $providerData)
    {
        foreach (array ('id', 'endpoint') as $key) {
            if (!isset($providerData[$key])) {
                throw new ProviderException(sprintf("%s must be set", $key));
            }
        }

        $c = new static($providerData['id'], $providerData['endpoint']);

        if (isset($providerData['name'])) {
            $c->setName($providerData['name']);
        }

        if (isset($providerData['basic_user'])) {
            $c->setBasicUser($providerData['basic_user']);
        }

        if (isset($providerData['basic_pass'])) {
            $c->setBasicPass($providerData['basic_pass']);
        }

        if (isset($providerData['filter'])) {
            $c->setFilter($providerData['filter']);
        }

        if (isset($providerData['contact_email'])) {
            $c->setContactEmail($providerData['contact_email']);
        }

        return $c;
    }

    public function setId($i)
    {
        if (!is_string($i) || empty($i)) {
            throw new ProviderException("id must be non empty string");
        }
        $this->_data['id'] = $i;
    }

    public function getId()
    {
        return $this->_data['id'];
    }

    public function setEndpoint($r)
    {
        if (!is_string($r) || empty($r)) {
            throw new ProviderException("endpoint must be non empty string");
        }

        if (FALSE === filter_var($r, FILTER_VALIDATE_URL)) {
            throw new ProviderException("endpoint should be valid URL");
        }
        // not allowed to have a fragment (#) in it
        if (NULL !== parse_url($r, PHP_URL_FRAGMENT)) {
            throw new ProviderException("endpoint cannot contain a fragment");
        }
        $this->_data['endpoint'] = $r;
    }

    public function getEndpoint()
    {
        return $this->_data['endpoint'];
    }

    public function setName($n)
    {
        if (!is_string($n) || empty($n)) {
            throw new ProviderException("name must be non empty string");
        }
        $this->_data['name'] = $n;
    }

    public function getName()
    {
        return isset($this->_data['name']) ? $this->_data['name'] : FALSE;
    }

    public function setBasicUser($s)
    {
        if (1 !== preg_match(self::REGEXP_VSCHAR, $s)) {
            throw new ProviderException("invalid character(s) in 'basic_user'");
        }
        if (FALSE !== strpos($s, ":")) {
            throw new ProviderException("invalid character in 'basic_user'");
        }
        $this->_data['basic_user'] = $s;
    }

    public function getBasicUser()
    {
        return isset($this->_data['basic_user']) ? $this->_data['basic_user'] : FALSE;
    }

    public function setBasicPass($s)
    {
        if (1 !== preg_match(self::REGEXP_VSCHAR, $s)) {
            throw new ProviderException("invalid character(s) in 'basic_pass'");
        }
        if (FALSE !== strpos($s, ":")) {
            throw new ProviderException("invalid character in 'basic_pass'");
        }
        $this->_data['basic_pass'] = $s;
    }

    public function getBasicPass()
    {
        return isset($this->_data['basic_pass']) ? $this->_data['basic_pass'] : FALSE;
    }

    public function setFilter(array $filter)
    {
        // filter must be array with strings
        foreach ($filter as $f) {
            if (!is_string($f)) {
                throw new ProviderException("filter entries must be strings");
            }
        }
        $this->_data['filter'] = array_values($filter);
    }

    public function getFilter()
    {
        return isset($this->_data['filter']) ? $this->_data['filter'] : FALSE;
    }

    public function setContactEmail($c)
    {
        if (!is_string($c) || empty($c)) {
            throw new ProviderException("contact_email must be non empty string");
        }
        if (FALSE === filter_var($c, FILTER_VALIDATE_EMAIL)) {
            throw new ProviderException("contact_email must be a valid email address");
        }
        $this->_data['contact_email'] = $c;
    }

    public function getContactEmail()
    {
        return isset($this->_data['contact_email']) ? $this->_data['contact_email'] : FALSE;
    }

    public function getProviderAsArray()
    {
        return $this->_data;
    }

    // one or more elements from data must be in filter to pass
    // PASS: array("foo"), array("foo", "bar")
    // FAIL: array("foo"), array("bar", "baz")
    // FAIL: array(), array("foo", "bar")
    // PASS: array("foo"), array()
    public function passFilter(array $toFilter)
    {
        $filter = $this->getFilter();
        if (FALSE === $filter || 0 === count($filter)) {
            // no filter defined, so everything passes
            return TRUE;
        }
        // filter defined, with elements
        foreach ($toFilter as $e) {
            if (in_array($e, $filter)) {
                return TRUE;
            }
        }

        return FALSE;
    }

}
