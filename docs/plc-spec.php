<?php

require(__DIR__ . '/did-spec.php');

// See:
//   * https://github.com/did-method-plc/did-method-plc
//   * https://github.com/multiformats/cid - Content Identifier specification

// plc.directory endpoints (see https://web.plc.directory/api/redoc)
//
//   * https://plc.directory/{did}
//   * https://plc.directory/{did}/log
//   * https://plc.directory/{did}/log/audit
//   * https://plc.directory/{did}/log/last
//   * https://plc.directory/{did}/data
//   * https://plc.directory/export?count=1000&after=2025-01-01
//   * https://web.plc.directory/did/{did}
//
// sample DIDs:
//   * did:plc:ewvi7nxzyoun6zhxrhs64oiz - at://atproto.com
//   * did:plc:afjf7gsjzsqmgc7dlhb553mv - Git Updater FAIR package

// Note how the fields _almost_ line up with CoreDocument fields, but not quite.
// Instead of 'id' we have 'did', 'services' (note the plural) is a Map, not a Set, and so on.

// It's also quite unlikely we'll use an object to pass operation parameters, these are just for documentation
// https://github.com/did-method-plc/did-method-plc#how-it-works
interface PLCOperationParameters {
    public string|null $did {get;}               // not passed in the genesis operation (it doesn't exist yet)
    public Set|null $rotationKeys {get;}         // Set<DID>, min 1, max 5 (must be k256 or p256 keys)
    public Map|null $verificationMethods {get;}  // Map<string, DID> (must be did:key, limit 10 entries)
    public Set|null $alsoKnownAs {get;}          // Set<URI>
    public Map|null $services {get;}             // Map<string, {string type, string endpoint}>
}

// The spec doesn't specify a 'did' parameter for update ops, possibly that's what $prev is for?

interface CreateOrUpdateOperation {
    public string $type {get;}      // always 'plc_operation'
    public Set|null $rotationKeys {get;}
    public Map|null $verificationMethods {get;}
    public Set|null $alsoKnownAs {get;}
    public Map|null $services {get;}
    public string|null $prev {get;} // CID hash pointer to previous op, or EXPLICIT null (not omitted) on create
    public string $sig {get;}       // base64url encoded signature
}

interface TombstoneOperation {
    public string $type {get;} // always 'plc_tombstone'
    public string $prev {get;} // not nullable
    public string $sig {get;}
}

// FAIR doesn't need to support legacy operations, but they exist in the directory, so this is the format
interface LegacyCreateOperation {
    public string $type {get;}     // always 'create'
    public DID $signingKey {get;}  // a single did:key, maps to verificationMethods
    public DID $recoveryKey {get;} // a single did:key, maps to rotationKeys
    public string $handle {get;}   // atproto handle without at:// prefix, maps to alsoKnownAs
    public URL $service {get;}     // https url to atproto PDS
    public null $prev {get;}       // always null, but always included
    public string $sig {get;}
}


//////////////// Operational Notes

// * Operations are encoded with DAG-CBOR with a max length of 7500 bytes
// * prev references are CIDv1, with base32 multibase encoding, dag-cbor multibase type, and sha-256 multihash
// * rotation keys are in order of authority, highest first
// * higher-authority keys can clobber ops from a lower authority by using the op's parent in 'prev' in a 72h window

