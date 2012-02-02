<?php
/**
 * Object representation of a RFC 822 e-mail address.
 *
 * Copyright 2012 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/bsd New BSD License
 * @package   Mail
 */

/**
 * Object representation of a RFC 822 e-mail address.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/bsd New BSD License
 * @package   Mail
 */
class Horde_Mail_Rfc822_Address
{
    /**
     * Comments associated with the personal phrase.
     *
     * @var array
     */
    public $comment = array();

    /**
     * Hostname of the address.
     *
     * @var string
     */
    public $host = null;

    /**
     * Local-part of the address.
     *
     * @var string
     */
    public $mailbox = null;

    /**
     * Personal part of the address.
     *
     * @var string
     */
    public $personal = null;

    /**
     * Routing information (obsoleted by RFC 2822 [4.4]).
     *
     * @deprecated
     *
     * @var array
     */
    public $route = array();

    /**
     */
    public function __get($name)
    {
        switch ($name) {
        case 'adl':
            // DEPRECATED
            return empty($route)
                ? ''
                : implode(',', $route);
        }
    }

}