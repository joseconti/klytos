# Third-Party Licenses — vendor-ai/

This directory contains vendorized PHP dependencies used by the Klytos AI Chat module.
These are pre-installed and do not require Composer at runtime.

**16 packages**, reconstructed and pinned in `installer/composer.json` (see `docs/decisions.md` D-028).
Composer's own record of what is installed lives in `composer/installed.json` and
`composer/installed.php`; `tests/Unit/VendorAiManifestTest.php` fails the suite if this list ever
drifts from it.

Most packages ship their own `LICENSE` file next to their source. Two do not — upstream simply omits
it — so their license text is reproduced in full at the end of this file: `soukicz/llm` and
`phplang/scope-exit`, both BSD.

## soukicz/llm (v0.5.0)
- License: BSD-3-Clause — **full text below** (upstream ships no `LICENSE` file)
- Copyright (c) Petr Soukup
- https://github.com/soukicz/php-llm

## guzzlehttp/guzzle (v7.10.0)
- License: MIT
- Copyright (c) Michael Dowling and contributors
- https://github.com/guzzle/guzzle

## guzzlehttp/promises (v2.3.0)
- License: MIT
- Copyright (c) Michael Dowling and contributors
- https://github.com/guzzle/promises

## guzzlehttp/psr7 (v2.9.0)
- License: MIT
- Copyright (c) Michael Dowling and contributors
- https://github.com/guzzle/psr7

## psr/http-message (v2.0)
- License: MIT
- Copyright (c) PHP-FIG
- https://github.com/php-fig/http-message

## psr/http-client (v1.0.3)
- License: MIT
- Copyright (c) PHP-FIG
- https://github.com/php-fig/http-client

## psr/http-factory (v1.1.0)
- License: MIT
- Copyright (c) PHP-FIG
- https://github.com/php-fig/http-factory

## ralouphie/getallheaders (v3.0.3)
- License: MIT
- Copyright (c) Ralph Khattar
- https://github.com/ralouphie/getallheaders

## ramsey/uuid (v4.9.2)
- License: MIT
- Copyright (c) Ben Ramsey
- https://github.com/ramsey/uuid

## ramsey/collection (v2.1.1)
- License: MIT
- Copyright (c) Ben Ramsey
- https://github.com/ramsey/collection

## swaggest/json-schema (v0.12.43)
- License: MIT
- Copyright (c) Viacheslav Poturaev
- https://github.com/swaggest/php-json-schema

## swaggest/json-diff (v3.12.1)
- License: MIT
- Copyright (c) Viacheslav Poturaev
- https://github.com/swaggest/json-diff

## phplang/scope-exit (v1.0.0)
- License: BSD — **full text below** (upstream ships no `LICENSE` file)
- Copyright (c) Sara Golemon
- https://github.com/phplang/scope-exit

## brick/math (v0.14.8)
- License: MIT
- Copyright (c) Benjamin Morel
- https://github.com/brick/math

## symfony/polyfill-mbstring (v1.33.0)
- License: MIT
- Copyright (c) Fabien Potencier
- https://github.com/symfony/polyfill-mbstring

## symfony/deprecation-contracts (v3.6.0)
- License: MIT
- Copyright (c) Fabien Potencier
- https://github.com/symfony/deprecation-contracts

---

## BSD-3-Clause license text

Applies to `soukicz/llm` (Copyright (c) Petr Soukup) and `phplang/scope-exit`
(Copyright (c) Sara Golemon), reproduced here because neither package ships a
`LICENSE` file of its own.

```
Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.

3. Neither the name of the copyright holder nor the names of its contributors
   may be used to endorse or promote products derived from this software without
   specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
```
