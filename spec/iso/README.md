# What this specification is written against

ISO/IEC 39075:2024 defines conformance in its clause 24, and publishes part of what a
conformance claim needs as machine-readable *digital artifacts*. Each file's own header
states its purpose in ISO's words, and every one of them says it is there to be used.

| File | What it is | Used for |
|------|------------|----------|
| `features.xml` | ISO digital artifact: every optional language feature the standard defines, by code and description | the claim under 24.3, "Conformance to features" — `spec/features/conformance.feature` answers for every one of the 228 codes |
| `conditions.xml` | ISO digital artifact: every condition a GQL-implementation may report, by GQLSTATUS code | the claim about clause 23 — no code peq reports is absent from this list |
| `gql.bnf.xml` | ISO digital artifact: the grammar, as the XML vocabulary the standard itself is written in | the claim about clause 21 — the words peq reserves are `<reserved word>` and `<pre-reserved word>`, and no word peq spells is outside the grammar |
| `implementation-defined.xml` | ISO digital artifact: every item the standard leaves for an implementation to define | the claim under 24.5 — what peq settles for itself is something the standard left it to settle, such as IL018 and `--hops` |
| `subclauses.txt` | *not* an ISO artifact: the clause and subclause structure, transcribed from the table of contents of the publicly downloadable preview | the tags — a scenario says which subclause it states, and the number is looked up here |

ISO publishes no executable test suite for GQL, and no other body publishes one ISO
recognises, so there is nothing to run and claim a pass from. What there is, is the
standard's own enumerations. A claim checked against those is a claim anybody can
re-check, which is the most a conformance claim can be without a certifying body.

Each artifact's header states, in ISO's own words, that it is published for implementers
to use — the grammar "may be used by implementers of GQL-implementations when generating
parsers", the implementation-defined list "to ensure that the definition of every such
implementation-defined item is known". Using them for exactly that is why they are here.

## Provenance

The four XML files are byte-for-byte the artifacts ISO publishes, taken from
<https://github.com/tamnd/gql-compat> (`iso/artifacts/`), which vendors them with
checksums. The SHA-256 sums in `SHA256SUMS` are the ones that repository records, so a
reader can verify this copy has not drifted from ISO's.

`composer spec` checks those sums on every run, which is the step that makes the rest of
the suite worth anything: an artifact quietly edited until it agreed with `src/` would
otherwise let every other step pass. It catches drift rather than forgery — the remedy
for a sum edited alongside its file is the URL above, which anybody can fetch.

`subclauses.txt` carries its own provenance in its header: it is transcribed from the
contents pages of the free preview ISO and its resellers publish, and reproduces numbers
and titles only.
