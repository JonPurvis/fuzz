# Local PoC consumer

Tiny app-shaped project that path-requires `jonpurvis/fuzz` (same pattern as installing into Laravel).

```bash
cd examples/poc
composer install
./vendor/bin/pest
```

- `HeadKey`: datasets stay green on known JSON; fuzz finds `{}` / `null` / etc.
- `PricingEngine`: datasets stay green on known quotes and regress `100,0`; fuzz finds other hostile denominators
