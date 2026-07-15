# Upgrade Guide

Breaking changes land only in majors, and each major gets a section
here. Every entry follows one format:

> **What changed** / **Why** (ADR link) / **Before** / **After** /
> **Estimated effort**

Additive features in minor versions get [CHANGELOG](CHANGELOG.md)
entries only.

A note on stability boundaries (NFR-08): the contracts in
`Syriable\Casework\Contracts`, the facade surface, events, exceptions,
and config keys are the public API. Concrete action class names and
constructor signatures are *replaceable but not stable* — subclasses
decorating an action should call the parent rather than copy
internals; any action-internal change that could affect such
subclasses will be flagged here.

## v1

Initial release — nothing to upgrade from.
