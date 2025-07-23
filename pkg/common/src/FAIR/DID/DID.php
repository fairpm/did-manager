<?php

/** @noinspection ForgottenDebugOutputInspection */

namespace FAIR\DID;

use Elliptic\EC\KeyPair;
use Exception;
use FAIR\DID\PLC\Operation;
use FAIR\DID\PLC\SignedOperation;
use MiniFAIR\PLC\JunkDrawer\Keys;
use MiniFAIR\PLC\JunkDrawer\Operations;

/** Partial port, work in progress */
class DID
{
    const DIRECTORY_API = 'https://plc.directory';

    const POST_TYPE = 'plc_did';
    const META_DID = 'plc_did';
    const META_ROTATION_KEYS = 'plc_did_rotation_keys';
    const META_VERIFICATION_KEYS = 'plc_did_verification_keys';

    const METHOD_FAIRPM = 'fairpm';

    public readonly string $id;
    protected ?int $internal_id = null;

    /**
     * Has this DID been registered?
     */
    protected bool $created = false;

    /**
     * Rotation keys.
     *
     * These keys are used to manage the PLC entry itself.
     *
     * @var string[]
     */
    protected array $rotation_keys = [];

    /**
     * Verification keys.
     *
     * These keys are used to verify content belonging to the DID.
     *
     * @var string[]
     */
    protected array $verification_keys = [];

    protected ?string $prev = null;

    /**
     * @return KeyPair[]
     */
    public function get_rotation_keys(): array
    {
        return array_map(fn($key) => Keys::decode_private_key($key), $this->rotation_keys);
    }

    /**
     * @return KeyPair[]
     */
    public function get_verification_keys(): array
    {
        return array_map(fn($key) => Keys::decode_private_key($key), $this->verification_keys);
    }

    /**
     * Get the internal post ID for this DID.
     *
     * Only use this if you absolutely need it.
     *
     * @return int|null
     */
    public function get_internal_post_id(): ?int
    {
        return $this->internal_id;
    }

    public function save()
    {
        $this->_not_implemented();
    }

    protected function perform_operation(SignedOperation $op)
    {
        $this->_not_implemented();
    }

    public function update()
    {
        $op = $this->prepare_update_op();
        if (!$op) {
            // no changes
            return;
        }

        // Perform the operation.
        return $this->perform_operation($op);
    }

    protected function get_package_endpoint()
    {
        // return rest_url( API\REST_NAMESPACE . '/packages/' . $this->id )
        $this->_not_implemented();
    }

    protected function prepare_update_op(): ?SignedOperation
    {
        // Fetch the previous op.
        $last_op = $this->fetch_last_op();

        // Get it as a CID.
        $last_cid = Operations::cid_for_operation($last_op);

        $endpoint = $this->get_package_endpoint();

        // Merge prior data with current data.
        $update_unsigned = new Operation(
            type: 'plc_operation',
            rotationKeys: $this->get_rotation_keys(),
            verificationMethods: [
                self::METHOD_FAIRPM => $this->get_verification_keys()[0],
            ],
            alsoKnownAs: $last_op->alsoKnownAs,
            services: [
                'fairpm_repo' => [
                    'endpoint' => $endpoint,
                    'type' => 'FairPackageManagementRepo',
                ],
            ],
            prev: $last_cid,
        );

        // Check if we have any differences.
        if (
            $update_unsigned->rotationKeys === $last_op->rotationKeys
            && $update_unsigned->verificationMethods === $last_op->verificationMethods
            && $update_unsigned->alsoKnownAs === $last_op->alsoKnownAs
            && $update_unsigned->services === $last_op->services
        ) {
            // No changes, no need to update.
            return null;
        }

        // Sign it using our key.
        $update_signed = $update_unsigned->sign($this->get_rotation_keys()[0]);

        return $update_signed;
    }

    /**
     * @return Operation
     * @throws Exception
     */
    public function fetch_last_op(): Operation
    {
        $url = sprintf('%s/%s/log/last', static::DIRECTORY_API, $this->id);
        // $data = HTTP::get($url);
        $data = [];


        // Convert the last op into an Operation.
        $last_op = new Operation(
            type: $data['type'],
            rotationKeys: array_map(fn($key) => Keys::decode_did_key($key), $data['rotationKeys']),
            verificationMethods: array_map(fn($key) => Keys::decode_did_key($key), $data['verificationMethods']),
            alsoKnownAs: $data['alsoKnownAs'],
            services: $data['services'],
            prev: $data['prev'],
        );
        $last_op_signed = new SignedOperation(
            $last_op,
            $data['sig'],
        );

        $this->_not_implemented();
        // return $last_op_signed;
    }

    /**
     * @return array
     */
    public function fetch_audit_log()
    {
        $url = sprintf('%s/%s/log/audit', static::DIRECTORY_API, $this->id);
        // return HTTP::get($url);
    }

    public function refresh()
    {
        $url = sprintf( '%s/%s', static::DIRECTORY_API, $this->id );
        // return HTTP::get($url, ['headers' => ['Accept' => 'application/did+ld+json']]);
        $this->_not_implemented();
    }

    public function is_published()
    {
        $this->refresh();
        $url = sprintf('https://plc.directory/%s', $this->id);
        // $response = HTTP::get($url, ['headers' => ['Accept' => 'application/did+ld+json']]);
        // return $response->status === 200;
        $this->_not_implemented();
    }

    public static function get(string $id)
    {
        self::_not_implemented();
    }

    public static function from_post(mixed $post)
    {
        self::_not_implemented();
    }

    public static function from_internal_id($id)
    {
        self::_not_implemented();
    }

    public static function create()
    {
        $did = new self();

        // Generate an initial keypair for rotation.
        $rotation_key = Keys::generate_keypair();
        $encoded_rotation_key = Keys::encode_private_key($rotation_key, Keys::CURVE_K256);
        $did->rotation_keys = [
            $encoded_rotation_key,
        ];

        // Generate an initial keypair for verification.
        $verification_key = Keys::generate_keypair();
        $encoded_verification_key = Keys::encode_private_key($verification_key, Keys::CURVE_K256);
        $did->verification_keys = [
            $encoded_verification_key,
        ];

        // Create the genesis operation.
        $genesis_unsigned = new Operation(
            type: 'plc_operation',
            rotationKeys: [
                $rotation_key,
            ],
            verificationMethods: [
                self::METHOD_FAIRPM => $verification_key,
                // 'atproto' => $verification_key,
            ],
            alsoKnownAs: [],
            services: [],
        // 'services' => [
        // 	'fairpm_repo' => [
        // 		'serviceEndpoint' => 'https://fairpm.example.com/repo',
        // 		'type' => 'FairPackageManagementRepo',
        // 	],
        // ],
        // services: [
        // 	'atproto_pds' => [
        // 		'endpoint' => 'https://example.com/pds',
        // 		'type' => 'AtprotoPersonalDataServer',
        // 	],
        // ],
        );

        // Sign the op, then generate the DID from it.
        $genesis_signed = $genesis_unsigned->sign($rotation_key);
        $did_chars = Operations::genesis_to_plc($genesis_signed);
        $did_id = sprintf('did:plc:%s', $did_chars);

        $did->id = $did_id;
        $did->perform_operation($genesis_signed);
        $did->save();
        return $did;
    }

    // temporary replacements
    private static function _not_implemented(): never
    {
        throw new \RuntimeException("not implemented");
    }
}
