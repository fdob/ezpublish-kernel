# Backwards compatibility changes

Changes affecting version compatibility with former or future versions.


## Changes

### API

* Binary FieldTypes (Image, BinaryFile, Media) Value

  The path property was renamed to id (see EZP-20948)
  path remains available for both input and output, as a BC facilitator,
  but will be removed in a further major version and should not be used

## Deprecations

* Binary FieldTypes (Image, BinaryFile, Media) Value

  As explained in API, the 'path' property for those classes is deprecated,
  and 'id' should be used instead.


## Removals

No removals at the time of writing.

No further changes known in this release at time of writing.
See online on your corresponding eZ Publish version for
updated list of known issues (missing features, breaks and errata).
