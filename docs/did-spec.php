<?php
/**
 * Documents used, in order of precedence.
 *
 *   * https://www.w3.org/TR/did-resolution/  (Draft 2025-08-03)
 *   * https://www.w3.org/TR/did-1.1/         (Draft 2025-07-31)
 *   * https://www.w3.org/TR/cid-1.0          (Recommendation 2025-05-15)
 *   * https://www.w3.org/TR/did-1.0/         (Recommendation 2022-07-19) TODO: replace with did-core
 *
 *   The interfaces here are for sake of example, and are not intended to be the final implementation.
 *   Names of fields and most interfaces do try to match the specification as much as possible.
 *
 *  All subtypes of URI, including URL, DID and, DIDURL are represented as strings in the DID data model
 *
 *  A Set without a type comment after is assumed to contain the union of types to its left
 *  In other words, Foo|Bar|Set|null is Foo|Bar|Set<Foo|Bar>|null
 *
 *  All nullable properties are assumed to be optional and have a default of null (a DTO might use Optional instead)
 *  Unless stated otherwise, a prop that is null is entirely absent from inputs and outputs, not set to the null value.
 *
 * @noinspection PhpUnused
 */

////////////////

// https://www.w3.org/TR/did-1.1/#did-syntax
interface DID extends URI
{
    public string $method {get;}       // [a-z0-9]+
    public string $identifier {get;}   // aka "method-specific-id".  colon separated url-encoded segments.
    public string|null $query {get;}
    public string|null $fragment {get;}
}

interface DIDURL extends DID {
    public string $path {get;}
}

//////////////// Documents

// The interfaces here are a minimal read-only specification, but there is no One Right Way to represent all Documents.

interface Document {
    public DID $id {get;}
}

// https://www.w3.org/TR/did-1.1/#core-properties
interface CoreDocument extends Document
{
    public DID|Set|null $controller {get;}       // set<DID> https://www.w3.org/TR/did-1.1/#did-controller
    public Set|null $alsoKnownAs {get;}          // set<URL|DID> https://www.w3.org/TR/cid-1.0/#also-known-as
    public Set|null $service {get;}              // set<Service> https://www.w3.org/TR/cid-1.0/#services
    public Set|null $verificationMethod {get;}   // set<VerificationMethod> https://www.w3.org/TR/did-1.1/#verification-methods
    public Set|null $authentication {get;}       // set<DID|VerificationMethod> https://www.w3.org/TR/cid-1.0/#authentication
    public Set|null $assertionMethod {get;}      // set<DID|VerificationMethod> https://www.w3.org/TR/cid-1.0/#assertion
    public Set|null $keyAgreement {get;}         // set<DID|VerificationMethod> https://www.w3.org/TR/cid-1.0/#key-agreement
    public Set|null $capabilityInvocation {get;} // set<DID|VerificationMethod> https://www.w3.org/TR/cid-1.0/#capability-invocation
    public Set|null $capabilityDelegation {get;} // set<DID|VerificationMethod> https://www.w3.org/TR/cid-1.0/#capability-delegation
}

//////////////// Verification Methods

// https://www.w3.org/TR/did-1.1/#verification-methods
// https://www.w3.org/TR/cid-1.0/#verification-methods
interface VerificationMethod
{
    public DID $id {get;}            // CID spec allows any URL, DID spec requires a DID
    public string $type {get;}       // 'JsonWebKey' | 'Multikey' per CID spec
    public DID $controller {get;}    // CID spec allows any URL, DID spec requires a DID
    public Date|null $expires {get;}
    public Date|null $revoked {get;}
}

// https://www.w3.org/TR/cid-1.0/#Multikey
interface MultikeyVerificationMethod extends VerificationMethod
{
    public string|null $publicKeyMultibase {get;}
    public string|null $privateKeyMultibase {get;}
}

// https://www.w3.org/TR/cid-1.0/#JsonWebKey
interface JsonWebKeyVerificationMethod extends VerificationMethod
{
    public Map|null $publicKeyJwk {get;}
    public Map|null $privateKeyJwk {get;}
}

//////////////// Services

// https://www.w3.org/TR/cid-1.0/#services
interface Service
{
    public URI|null $id {get;}
    public string|Set $type {get;}             // CID spec recommends registering in https://www.w3.org/TR/vc-extensions/
    public URL|Map|Set $serviceEndpoint {get;} // Map format is apparently free-form
}

//////////////// Resolution

// https://www.w3.org/TR/did-1.0/#did-resolution
interface Resolver
{
    // this should be the general idea...
    public function __invoke(DID $did): Document;

    // This is what the spec wants according to https://www.w3.org/TR/did-core/#did-resolution

    // resolve(did, resolutionOptions) →
    //    « didResolutionMetadata, didDocument, didDocumentMetadata »
    public function resolve(DID $did, ResolutionOptions $resolutionOptions): ResolveResult;

    // resolveRepresentation(did, resolutionOptions) →
    //    « didResolutionMetadata, didDocumentStream, didDocumentMetadata »
    public function resolveRepresentation(DID $did, ResolutionOptions $resolutionOptions): ResolveRepresentationResult;

    // https://www.w3.org/TR/did-1.0/#did-url-dereferencing
    // dereference(didUrl, dereferenceOptions) →
    //    « dereferencingMetadata, contentStream, contentMetadata »
    public function dereference(DIDURL $url, DereferencingOptions $dereferenceOptions): DereferencingResult;
}

interface ResolveResult
{
    public DIDResolutionMetadata $didResolutionMetadata {get;}
    public Document $didDocument {get;}
    public array $didDocumentMetadata {get;}
}

interface ResolveRepresentationResult
{
    public array $didResolutionMetadata {get;}
    public ByteStream $didDocumentStream {get;}
    public array $didDocumentMetadata {get;}
}

// https://www.w3.org/TR/did-resolution/#did-resolution-options
interface ResolutionOptions
{
    public array $accept = [] {get;}
    public bool $expandRelativeUrls = false {get;}
    public string|null $versionId {get;}
    public Date|null $versionTime {get;}
}

// https://www.w3.org/TR/did-1.0/#did-resolution-metadata
interface DIDResolutionMetadata
{
    public string|null $contentType {get;}
    public ResolutionError|null $error {get;}
}

// The mapping to URLs is probably best done externally, not as their ->value()
enum ResolutionError: string
{
    // These come from did-resolution: their value is the URL for the 'type' field
    // http status codes in comments come from the table at https://www.w3.org/TR/did-resolution/#bindings-https
    case INVALID_DID = 'https://www.w3.org/ns/did#INVALID_DID'; // 400
    case INVALID_DID_DOCUMENT = 'https://www.w3.org/ns/did#INVALID_DID_DOCUMENT'; // 500
    case NOT_FOUND = 'https://www.w3.org/ns/did#NOT_FOUND'; // 404
    case REPRESENTATION_NOT_SUPPORTED = 'https://www.w3.org/ns/did#REPRESENTATION_NOT_SUPPORTED'; // 406
    case INVALID_DID_URL = 'https://www.w3.org/ns/did#INVALID_DID_URL'; // 400
    case METHOD_NOT_SUPPORTED = 'https://www.w3.org/ns/did#METHOD_NOT_SUPPORTED'; // 501
    case INVALID_OPTIONS = 'https://www.w3.org/ns/did#INVALID_OPTIONS'; // 400
    case INTERNAL_ERROR = 'https://www.w3.org/ns/did#INTERNAL_ERROR'; // 500

    case INVALID_PUBLIC_KEY = 'https://w3id.org/security#INVALID_PUBLIC_KEY'; // 500
    case INVALID_PUBLIC_KEY_LENGTH = 'https://w3id.org/security#INVALID_PUBLIC_KEY_LENGTH'; // 500
    case INVALID_PUBLIC_KEY_TYPE = 'https://w3id.org/security#INVALID_PUBLIC_KEY_TYPE'; // 500
    case UNSUPPORTED_PUBLIC_KEY_TYPE = 'https://w3id.org/security#UNSUPPORTED_PUBLIC_KEY_TYPE'; // 501

    // default http code for any unspecified error is 500
}

// https://www.w3.org/TR/did-1.0/#did-document-metadata
interface DocumentMetadata
{
    public Date|null $created {get;}
    public Date|null $updated {get;}
    public Date|null $deactivated {get;}
    public Date|null $nextUpdate {get;}
    public string|null $versionId {get;}
    public string|null $nextVersionId {get;}
    public DID|null $equivalentId {get;}
    public DID|null $canonicalId {get;}
}

// Note the use of "Dereferencing" for a prefix instead of just "Dereference", to be consistent with the spec.

// https://www.w3.org/TR/did-1.0/#did-url-dereferencing-options
// https://www.w3.org/TR/did-resolution/#did-url-dereferencing-options
interface DereferencingOptions
{
    public string|null $accept {get;}                   // from base spec
    public string|null $verificationRelationship {get;} // from did-resolution
}

interface DereferencingResult
{
    public DereferencingMetadata $dereferencingMetadata {get;}
    public ByteStream $contentStream {get;}
    public ContentMetadata $contentMetadata {get;}
}

// https://www.w3.org/TR/did-1.0/#did-url-dereferencing-metadata
interface DereferencingMetadata
{
    public string|null $contentType {get;}
    public ProblemDetails|null $error {get;}
}

// https://www.w3.org/TR/did-resolution/#did-url-content-metadata
interface ContentMetadata {}    // defines no properties

//////////////// Types not specific to DIDs

// https://www.rfc-editor.org/rfc/rfc9457

// Serve with Content-type: application/problem+json
// null fields should be absent, not literal nulls.
interface ProblemDetails {
    public URI|null $type {get;}      // assumed to be 'about:blank' if not present
    public int|null $status {get;}    // http status code
    public string|null $title {get;}
    public string|null $detail {get;}
    public URI|null $instance {get;}
}

// type about:blank is registered with the following:
//    Type URI:  about:blank
//    Title:  See HTTP Status Code
//    Recommended HTTP status code:  N/A
//    Reference:  RFC 9457

//////////////// Opaque Types

interface Date {}        // https://www.w3.org/TR/xmlschema11-2/#dateTime, e.g. an ISO8601 string
interface ByteStream {}  // could be a string containing the resource, or some stream object
interface URI {}         // likely to be a string in real life
interface URL {}         // also a string
interface Set {}         // likely to be an array in real life.  no duplicates, but assumed to be ordered.
interface Map {}         // object with arbitrary keys/values, probably an array in PHP

//////////////// DID Resolution Algorithm
// https://www.w3.org/TR/did-resolution/#resolving-algorithm

// resolve(did, resolutionOptions) →
//    « didResolutionMetadata, didDocument, didDocumentMetadata »

// constants like INVALID_DID are assumed to be instances of didResolutionMeta with 'error' set
// {...} appears in the spec as «[...]», indicating some valid but unspecified object

// function resolve(did, resolutionOptions): [didResolutionMeta, didDocument, didDocumentMetadata]
//      if not valid(did): return [INVALID_DID, null, {}]
//
//      method = did.method
//      if not supported(method): return [METHOD_NOT_SUPPORTED, null, {}]
//
//      registry = getVerifiableDataRegistry(method)
//      [found, doc, docmeta] = registry.Read(did, resolutionOptions)   // type of Read() is not fully specified
//      if not found: return [NOT_FOUND, null, {}]
//      if docmeta.deactivated: return [{...}, null, {deactivated: true, ...}] // http status 410
//
//      if resolutionOptions.expandRelativeUrls:
//          foreach section in [doc.services, doc.verificationMethods, doc.verificationRelationships]:
//              foreach item in section:
//                  resolveRelativeURLsToAbsolute(item) // per https://www.w3.org/TR/did-1.0/#relative-did-urls
//
//      return [{...}, doc, {contentType: docMeta.contentType, ...}]



//////////////// TODO: DID URL Dereferencing Algorithm
// https://www.w3.org/TR/did-resolution/#dereferencing
// https://www.w3.org/TR/did-resolution/#dereferencing-algorithm

// dereference(didUrl, dereferenceOptions) →
//    « dereferencingMetadata, contentStream, contentMetadata »



//////////////// Miscellaneous notes

// * alsoKnownAs
//    - arbitrary URI or list of them, they need not be DIDs
//    - not just for providing known aliases for the same document
//    - may resolve to different documents with a different data model


////////////////////////////////////////////////////////////////

// References:

//   * [Controlled Identifiers 1.0](https://www.w3.org/TR/cid-1.0/)
//   * [Decentralized Identifiers (DIDs) v1.0](https://www.w3.org/TR/did-core/)
//   * [JSON-LD 1.1](https://www.w3.org/TR/json-ld11/)
//   * [WHATWG Infra Standard](https://infra.spec.whatwg.org/)
//   * [WHATWG URL Standard](https://url.spec.whatwg.org/)
//   * [XML Schema Definition Language (XSD) 1.1 Part 2: Datatypes](https://www.w3.org/TR/xmlschema11-2/)

//   * [RFC3986 - Uniform Resource Identifier (URI): Generic Syntax](https://datatracker.ietf.org/doc/rfc3986/)
//   * [RFC5234 - Augmented BNF for Syntax Specifications](https://datatracker.ietf.org/doc/rfc5234/)
//   * [RFC6838 - Media Type Specifications and Registration Procedures](https://datatracker.ietf.org/doc/rfc6838/)
//   * [RFC8259 - The JavaScript Object Notation (JSON) Data Interchange Format](https://datatracker.ietf.org/doc/rfc8259/)
//   * [RFC9457 - Problem Details for HTTP APIs](https://datatracker.ietf.org/doc/rfc8259/)

//////////////// Other Links to move up to the above

//   * https://www.w3.org/TR/did-resolution/
//   * https://w3c.github.io/did-rubric/
//   * https://www.w3.org/TR/did-extensions/
//   * https://www.w3.org/TR/did-use-cases/
//   * https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml

