# What this specification is written against

ISO/IEC 39075:2024 defines conformance in its clause 24, and publishes part of what a
conformance claim needs as machine-readable *digital artifacts* on its Standards
Maintenance Portal, <https://standards.iso.org/iso-iec/39075/ed-1/en/>.

| Artifact | What it is | Used for |
|----------|------------|----------|
| `features.xml` | every optional language feature, by code and description | the register in `conformance.feature` answers for every one of the 228 codes |
| `conditions.xml` | every condition an implementation may report, by GQLSTATUS | no code peq reports is absent from it |
| `gql.bnf.xml` | the grammar, in the XML the standard itself is written in | the words peq reserves, the words it spells, the functions it offers, and the productions scenarios cite |
| `implementation-defined.xml` | every item the standard leaves an implementation to define | what peq settles for itself is something the standard left it to settle |

## These files are not in this repository

ISO publishes them under this notice:

> You are permitted to use the electronic insert(s) available on this site, in their
> original format without any modifications for the purposes specified in their
> respective ISO standard(s). When you download any electronic insert, you accept the ISO
> Customer Licence Agreement ("Licence Agreement"), clauses 1. ISO's Copyright,
> 7. Termination, 8. Limitations, and 9. Governing Law.

That grants use, not redistribution, and peq is MIT-licensed: a copy here would be ISO's
content offered under terms ISO did not give. So `artifacts.txt` lists where ISO
publishes each file and the SHA-256 it is published with, and `composer spec` downloads
each one into `build/iso/` the first time it needs it — which is also the moment the
person running it accepts ISO's licence for it — and checks the sum before anything reads
it. A file that is not byte-for-byte what ISO publishes stops the run.

## `subclauses.txt`

ISO publishes no artifact for the standard's own structure, and mandatory features carry
no feature code, so a subclause number is the only handle a scenario has on what it
states. `subclauses.txt` is the list of numbers the scenario tags are checked against,
read off the table of contents that the standard's free preview and ISO's Online Browsing
Platform both show. It keeps the numbers only; the titles are ISO's text.
