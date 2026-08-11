> You are in **splicewire/conduit-fda** — openFDA drug-shortage integration for Splicewire.

This is a Laravel package that provides an openFDA API integration via a Saloon connector and
request, with a manually-run command (`fda:sync-shortages`) that feeds the raw payload to
`splicewire/laravel-knowledge-engine`'s `OpenFdaShortageSource` and persists the resulting
`ConceptStatus` rows. Like the sibling `splicewire/conduit-ecfr`, this command is not scheduled
anywhere — no cron, no change-feed listener.
