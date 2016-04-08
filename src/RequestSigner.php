<?php

namespace Acquia\Hmac;

use Psr\Http\Message\RequestInterface;

class RequestSigner implements RequestSignerInterface
{
    /**
     * @var \Acquia\Hmac\Digest\DigestInterface
     */
    protected $digest;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var int
     */
    protected $timestamp;

    /**
     * @var array
     */
    protected $customHeaders = array();

    /**
     * @var string
     */
    protected $defaultContentType = 'application/json; charset=utf-8';

    /**
     * @var \Acquia\Hmac\AuthorizationHeader
     */
    protected $authorizationHeader;

    /**
     * @param \Acquia\Hmac\Digest\DigestInterface $digest
     */
    public function __construct(Digest\DigestInterface $digest = null, AuthorizationHeaderInterface $authorization_header = null)
    {
        $this->digest = $digest ?: new Digest\Version2();
        $this->authorizationHeader = $authorization_header ?: new AuthorizationHeader();
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthorizationHeader()
    {
        return $this->authorizationHeader;
    }

    /**
     * {@inheritDoc}
     */
    public function setDefaultContentType($content_type)
    {
        $this->defaultContentType = $content_type;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultContentType()
    {
        return $this->defaultContentType;
    }

    // @TODO 3.0 getters/setters at top.

    // @TODO 3.0 Interface/test
    public function signRequest(RequestInterface $request, $secretKey)
    {
        // @TODO 3.0 do we still need getters/setters for $id?
        if (!$request->hasHeader('X-Authorization-Timestamp')) {
            $request = $request->withHeader('X-Authorization-Timestamp', $this->getTimestamp());
        }

        if (!$request->hasHeader('Content-Type')) {
            $request = $request->withHeader('Content-Type', $this->getDefaultContentType());
        }

        if (!$request->hasHeader('X-Authorization-Content-SHA256')) {
            $hashed_body = $this->getHashedBody($request);
            if (!empty($hashed_body)) {
                $request = $request->withHeader('X-Authorization-Content-SHA256', $hashed_body);
            }
        }

        $authorization = $this->getAuthorization(
            $request,
            $this->getAuthorizationHeader()->getId(),
            $secretKey
        );
        $signed_request = $request->withHeader('Authorization', $authorization);
        return $signed_request;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Acquia\Hmac\Exception\MalformedRequest
     */
    public function getSignature(RequestInterface $request)
    {
        $id = $this->getAuthorizationHeader()->getId();
        $signature = $this->getAuthorizationHeader()->getSignature();
        $timestamp = $this->getTimestamp();

        if (empty($id)) {
            throw new Exception\KeyNotFoundException('Authorization header requires an id.');
        }

        if (empty($signature)) {
            throw new Exception\KeyNotFoundException('Authorization header requires a signature.');
        }

        // Ensure the signature is a base64 encoded string.
        if (!preg_match('@^[a-zA-Z0-9+/]+={0,2}$@', $signature)) {
            throw new Exception\MalformedRequestException('Invalid signature in authorization header');
        }

        return new Signature($id, $signature, $timestamp);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     * @throws \Acquia\Hmac\Exception\InvalidRequestException
     */
    public function getDigest(RequestInterface $request, $secretKey)
    {
        return $this->digest->get($this, $request, $secretKey);
    }

    // @TODO 3.0 Interface
    // @TODO 3.0 Test
    public function getHashedBody(RequestInterface $request)
    {
        $hash = '';
        if (!empty((string) $request->getBody())) {
            $hash = $this->digest->getHashedBody($request);
        }
        return $hash;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Acquia\Hmac\Exception\InvalidRequestException
     */
    public function getAuthorization(RequestInterface $request, $id, $secretKey)
    {
        // @TODO 3.0 creating the signature probably belongs elsewhere.
        $this->authorizationHeader->setSignature($this->getDigest($request, $secretKey));
        return $this->authorizationHeader->createAuthorizationHeader();
    }

    /**
     * {@inheritDoc}
     */
    public function getContentType(RequestInterface $request)
    {
        return $request->getHeaderLine('Content-Type');
    }

    /**
     * {@inheritDoc}
     */
    public function getTimestamp()
    {
        if (empty($this->timestamp)) {
            $time = new \DateTime();
            $time->setTimezone(new \DateTimeZone('GMT'));
            $this->timestamp = $time->getTimestamp();
        }

        return $this->timestamp;
    }

    /**
     * {@inheritDoc}
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = (int) $timestamp;
    }
}
