<?php

namespace FAIR\DID\PLC;

use Elliptic\EC\KeyPair;
use FAIR\DID\DID;
use JsonSerializable;
use MiniFAIR\PLC\JunkDrawer\Keys;
use MiniFAIR\PLC\JunkDrawer\Operations;
use RuntimeException;

class Operation implements JsonSerializable
{
    public function __construct(
        /**
         * Operation type (plc_operation or plc_tombstone)
         */
        public string $type,

        /**
         * Rotation keys.
         *
         * @var KeyPair[]
         */
        public array $rotationKeys,

        /**
         * Verification keys.
         *
         * @var array<string, KeyPair>
         */
        public array $verificationMethods,

        /**
         * Public key.
         *
         * @var string[]
         */
        public array $alsoKnownAs,

        /**
         * Services.
         *
         * @var array<string, string>
         */
        public array $services,

        /**
         * Previous operation.
         *
         * @var string|null
         */
        public ?string $prev = null,
    ) {}

    public function validate(): bool
    {
        if (empty($this->type)) {
            throw new RuntimeException('Operation type is empty');
        }
        if (!in_array($this->type, ['plc_operation', 'plc_tombstone'], true)) {
            throw new RuntimeException('Invalid operation type');
        }

        if (empty($this->rotationKeys)) {
            throw new RuntimeException('Rotation keys are empty');
        }
        foreach ($this->rotationKeys as $keypair) {
            if (!$keypair instanceof KeyPair) {
                throw new RuntimeException('Rotation key is not a KeyPair object');
            }
        }

        if (empty($this->verificationMethods)) {
            throw new RuntimeException('Verification methods are empty');
        }
        foreach ($this->verificationMethods as $key => $keypair) {
            if ($key !== DID::METHOD_FAIRPM) {
                throw new RuntimeException(sprintf('Invalid verification method ID: %s', $key));
            }
            if (!$keypair instanceof KeyPair) {
                throw new RuntimeException('Rotation key is not a KeyPair object');
            }
        }

        if (empty($this->prev) && empty($this->verificationMethods[DID::METHOD_FAIRPM])) {
            throw new RuntimeException('Missing verification method for FAIR');
        }

        return true;
    }

    public function sign(KeyPair $rotation_key): SignedOperation
    {
        return Operations::sign_operation($this, $rotation_key);
    }

    public function jsonSerialize(): array
    {
        $encode = fn($k) => Keys::encode_did_key($k, Keys::CURVE_K256);
        return [
            'type' => $this->type,
            'rotationKeys' => array_map($encode, $this->rotationKeys),
            'verificationMethods' => array_map($encode, $this->verificationMethods),
            'alsoKnownAs' => $this->alsoKnownAs,
            'services' => (object)$this->services,
            'prev' => $this->prev,
        ];
    }
}
