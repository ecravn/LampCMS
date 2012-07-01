<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is licensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 *       the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attributes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2012 (or current year) Dmitri Snytkine
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;


/**
 * Class for getting gravatar image
 * OR to check if email address has gravatar
 *
 * @author Dmitri Snytkine
 *
 */
class Gravatar
{

    const BASE_URL = '%1$s://www.gravatar.com/avatar/%2$s?s=%3$s&d=%4$s&r=%5$s';

    /**
     * Full Url of gravatar request
     *
     * @var string
     */
    protected $url;

    /**
     * Email address for which
     * we getting gravatar
     * @var string
     */
    protected $email;

    /**
     * String (binary data)
     * for the actual gravatar image
     * This is a string of binary jpg image
     *
     * @var string
     */
    protected $gravatar;

    /**
     * Protocol to use when downloading gravatar
     * http (default)
     *
     * @var string
     */
    protected $protocol = 'http';

    /**
     * MD5 Hash of lower-cased email address
     *
     * @var string
     */
    protected $emailHash;

    /**
     * Image file extension for gravatar
     * default is .jpg
     *
     * @var string
     */
    protected $ext = 'jpg';

    /**
     * Width/Height of gravatar
     *
     * @var string
     */
    protected $size = '75';

    /**
     * Rating of gravatar
     * @var string
     */
    protected $rating = 'g';

    /**
     * Fallback service to use for getting avatar
     * if real gravatar does not exist
     *
     * @var string
     */
    protected $fallback = 'wavatar';

    /**
     * Flag indication if
     * an email gas gravatar
     * @var string one of: Y for Definite Yes, N
     * for definite NO (when gravatar site returned http 404 response)
     * or 'U' for unknown -
     * this is when we cannot determine for sure
     * due to situations like this: gravatar site was
     * unavailable, so the request timedout
     *
     * Or the server returned the code 200 but
     * gravatar image was an empty string
     *
     * Also when this flag is not set
     * it indicates that fetchGravatar()
     * has not ran yet.
     */
    protected $gravatarExists;

    /**
     * Object HttpRequest holding an http responce
     *
     * @var object
     */
    protected $oResponse;

    /**
     * Constructor
     *
     * @param string $email
     */
    protected function __construct($email)
    {

        $this->setEmail($email);
    }


    /**
     * Factory method.
     * Only this method can be used
     * to create this object
     *
     * @param string     $email
     * @param int|string $size
     * @param string     $fallback
     * @param string     $rating
     *
     * @throws \InvalidArgumentException
     * @return object object of this class
     */
    public static function factory($email, $size = '75', $fallback = 'wavatar', $rating = 'g')
    {
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid value of $email: ' . $email);
        }

        if (!function_exists('curl_init') || !extension_loaded('curl')) {
            d('Your php does not have curl extension. Unable to fetch Gravatar. A dummy class will be used instead');

            return new Stub();
        }

        $o = new self($email);
        $o->setSize($size)
            ->setRating($rating)
            ->setFallback($fallback);

        return $o;
    }


    /**
     * Sets $this->email, and $this->emailHash
     * and unsets $this->url, $this->gravatar
     * and $this->gravatarExists
     *
     * @param string $email email address
     *
     * @throws \InvalidArgumentException if email address
     * fails validation by php's built in
     * email validation filter FILTER_VALIDATE_EMAIL
     * @return object $this
     */
    public function setEmail($email)
    {
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid value of $email: ' . $email);
        }

        $this->email = $email;
        $this->emailHash = hash('md5', strtolower($email));

        unset($this->url, $this->gravatar, $this->gravatarExists);

        return $this;
    }


    /**
     * Setter for $this->protocol
     * one of http or https
     *
     * @param string $protocol
     *
     * @throws \InvalidArgumentException
     * @return object $this
     */
    public function setProtocol($protocol = 'http')
    {
        $aAllowed = array('http', 'https');
        if (!in_array($protocol, $aAllowed)) {
            throw new \InvalidArgumentException('Invalid value of $ext: ' . $protocol . ' can only be one of these: ' . implode(' , ', $aAllowed));
        }

        $this->protocol = $protocol;

        return $this;
    }


    /**
     * Setter for $this->ext image extension
     * of of jpg, png or gif
     *
     * @param $ext
     *
     * @throws \InvalidArgumentException in case value
     * passed is not one of 3 supported image
     * extensions
     * @return object $this
     *
     */
    public function setExt($ext)
    {
        $aAllowed = array('jpg', 'png', 'gif');
        if (!in_array($ext, $aAllowed)) {
            throw new \InvalidArgumentException('Invalid value of $ext: ' . $ext . ' can only be one of these: ' . implode(' , ', $aAllowed));
        }

        $this->ext = $ext;

        return $this;
    }


    /**
     * Setter for $this->rating
     *
     * @param string $rating g, pg, r, or x
     *
     * @throws \InvalidArgumentException in case value
     * passed in not one of supported ratings
     * @return object $this
     */
    public function setRating($rating)
    {
        $aRatings = array('g', 'pg', 'r', 'x');
        $rating = strtolower($rating);

        if (!in_array($rating, $aRatings)) {
            throw new \InvalidArgumentException('Invalid value of $rating: ' . $rating . ' can only be one of these: ' . implode(' , ', $aRatings));
        }

        $this->rating = $rating;

        return $this;
    }


    /**
     * Setts the size of gravatar
     * we interested in getting
     *
     * @param string $size size in pixels or width and height
     *
     * @throws \OutOfRangeException
     * @throws \UnexpectedValueException
     * @return object $this
     */
    public function setSize($size)
    {
        if (!is_numeric($size)) {
            throw new \UnexpectedValueException('value of $size must be numeric, was: ' . $size);
        }


        if ($size < 25 || $size > 300) {
            throw new \OutOfRangeException('Value of $size should be between 25 and 300 (size of image in pixels). value given: ' . $size);
        }

        $this->size = $size;

        return $this;
    }


    /**
     * Sets on of the fallback services that return
     * some image in case real gravatar does not exist
     *
     * @param string $fallback name of fallback service set to 404
     *                         if you need to just test if avatar exists, in which case the server will
     *                         respond with 404 HTTP response instead of using fallback avatar
     *                         provider
     *
     * @throws \InvalidArgumentException
     * @return object $this
     */
    public function setFallback($fallback)
    {
        $aAllowed = array('identicon', 'monsterid', 'wavatar', '404');
        if (('http' !== substr($fallback, 0, 4)) && !in_array($fallback, $aAllowed)) {
            throw new \InvalidArgumentException('Invalid value of $fallback: ' . $fallback . ' can only be one of these: ' . implode(' , ', $aAllowed) . ' or must be a url that starts with http');
        }

        $this->fallback = $fallback;

        return $this;
    }


    /**
     * Setter for $this->url
     *
     * @return object $this
     */
    public function setAvatarUrl()
    {
        $this->url = vsprintf(self::BASE_URL, array($this->protocol, $this->emailHash, $this->size, $this->fallback, $this->rating));

        return $this;
    }


    /**
     * Getter for $this->url
     * @return string value of $this->url
     */
    protected function getUrl()
    {
        return $this->url;
    }


    /**
     * Test to see if email has
     * a gravatar.
     * For this we set the fallback to 404
     * then request a gravatar and then
     * if the gravatar's server returnes a 404 response then
     * we know that user does not have a gravatar.
     *
     * @return string value of $this->gravatarExists
     * which is either 'Y' or 'N' or 'U'
     */
    public function hasGravatar()
    {
        return $this->setFallback('404')
            ->setSize(25)
            ->setAvatarUrl()
            ->fetchGravatar()
            ->gravatarExists;
    }


    /**
     * Get actual avatar data (jpeg binary data)
     *
     * @param $since
     * @param $etag
     * @return unknown_type
     */
    public function fetchGravatar($since = null, $etag = null)
    {

        $oHTTP = new Curl();

        if (!isset($this->url)) {
            $this->setAvatarUrl();
        }

        try {

            $this->oResponse = $oHTTP->getDocument($this->url, $since, $etag)->checkResponse();
            $this->gravatar = $this->oResponse->getResponseBody();
            $this->gravatarExists = 'Y';
            d('has gravatar = Y');

        } catch (Exception $e) {
            $this->gravatarExists = 'U';
            if ($e instanceof Http404Exception) {
                d('No gravatar');
                $this->gravatarExists = 'N';
            }
        }

        return $this;
    }


    /**
     * Get the actual gravatar file
     * (binary string)
     *
     * @return mixed null if avatar does not
     * exist for email
     * or string (binary string) of the
     * avatar image.
     */
    public function getGravatar()
    {

        if (!isset($this->gravatarExists)) {
            $this->fetchGravatar();
        }

        if ('N' === $this->gravatarExists) {
            return null;
        }

        return $this->gravatar;
    }
}

