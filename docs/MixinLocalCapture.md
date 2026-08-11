# Local capture

Callback injections can select a Mixin-style local capture policy with the
`locals` argument:

```php
use Communism\Mixin\LocalCapture;

#[Inject('greet', new At('HEAD'), locals: LocalCapture::FAILSOFT)]
public function callback(CallbackInfo $info, string $optionalLocal): void {}
```

The policies are:

- `CAPTURE_FAILHARD` is the default and rejects an injection when a callback
  parameter cannot be mapped to a target argument or local.
- `FAILSOFT` skips that callback spot when capture fails.
- `NO_CAPTURE` permits target arguments but rejects additional local capture.
- `PRINT` reports the capture failure to `STDERR` and then fails the
  injection, which is useful while discovering a target frame.

All capture failures are handled before the rewritten opcode body is installed.
When a callback cannot capture its requested locals, Needle can select a
`#[Surrogate]` method named `<handler>Surrogate` with a compatible signature.
Full JVM-style local-frame and stack inspection remain internal Needle work.
