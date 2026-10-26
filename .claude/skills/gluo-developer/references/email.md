# Email: byjg/mailwrapper

Email sending is abstracted behind `MailWrapperInterface`. The active provider is selected
by a connection URI in the environment config — no code changes when switching providers.
The test environment uses `FakeSenderWrapper` (no-op) automatically.

---

## Sending an email

Retrieve the mailer and a pre-built envelope from the DI container, then send:

```php
use ByJG\Mail\Wrapper\MailWrapperInterface;

$mailer   = Config::get(MailWrapperInterface::class);
$envelope = Config::get('MAIL_ENVELOPE', [
    'user@example.com',            // To address
    'Welcome to the platform',     // Subject
    'welcome.html',                // Template file (under templates/emails/)
    ['name' => 'Jane', 'link' => 'https://example.com/activate/xxx'],
]);

$result = $mailer->send($envelope);
```

`MAIL_ENVELOPE` is a factory registered in `config/dev/06-external.php`. It:
1. Loads and renders the Jinja2 template from `templates/emails/`
2. Wraps it in an `Envelope` with the `EMAIL_TRANSACTIONAL_FROM` address
3. Adds an environment prefix to the subject when not in prod (e.g. `[dev] Welcome…`)

---

## Building an Envelope manually

Use this when you don't need a template or need full control:

```php
use ByJG\Mail\Envelope;

$envelope = new Envelope();
$envelope->setFrom('noreply@example.com', 'My App');
$envelope->addTo('user@example.com', 'Jane Doe');
$envelope->addCC('manager@example.com');
$envelope->addBCC('audit@example.com');
$envelope->setSubject('Your order has shipped');
$envelope->setBody('<h1>It\'s on its way!</h1>');   // HTML

$result = $mailer->send($envelope);
// $result->success  bool
// $result->id       string|null — provider message ID
```

### Attachments and embedded images

```php
// Attached file (shows as download)
$envelope->addAttachment('invoice.pdf', '/tmp/invoice-42.pdf', 'application/pdf');

// Embedded image (inline in HTML body)
$envelope->addEmbedImage('logo', '/var/www/assets/logo.png', 'image/png');
$envelope->setBody('<img src="cid:logo"> <p>Thanks for your order.</p>');
```

---

## Email templates

Templates live in `templates/emails/` and use **Jinja2 syntax**:

```html
<!-- templates/emails/welcome.html -->
<h1>Hi {{ name }},</h1>
<p>Click <a href="{{ link }}">here</a> to activate your account.</p>
```

Add new templates by creating a `.html` file in that directory. The factory strips the
`.html` extension — pass just `'welcome'` as the template name.

---

## Providers and connection URIs

Set `EMAIL_CONNECTION` in `config/{env}/credentials.env`:

| Provider | URI format |
|---|---|
| SMTP (plain) | `smtp://localhost:25` |
| SMTP + STARTTLS | `tls://user:pass@smtp.host.com:587` |
| SMTP + SSL | `ssl://user:pass@smtp.host.com:465` |
| Mailgun API | `mailgun://API_KEY@yourdomain.com` |
| Mailgun EU | `mailgun://API_KEY@yourdomain.com?region=eu` |
| Amazon SES | `ses://ACCESS_KEY:SECRET_KEY@us-east-1` |
| PHP mail() | `sendmail://localhost` |
| **Testing** | `fakesender://nothing` |

```ini
# config/dev/credentials.env
EMAIL_CONNECTION=tls://you@gmail.com:app-password@smtp.gmail.com:587
EMAIL_TRANSACTIONAL_FROM="My App <noreply@example.com>"

# config/test/credentials.env
EMAIL_CONNECTION=fakesender://nothing
```

---

## DI registration pattern

Providers are registered in `config/{env}/06-external.php`. The test environment
is already wired to `FakeSenderWrapper` — no extra setup needed for tests.

```php
// config/dev/06-external.php
use ByJG\Mail\MailerFactory;
use ByJG\Mail\Wrapper\MailWrapperInterface;
use ByJG\Mail\Wrapper\PHPMailerWrapper;
use ByJG\Mail\Wrapper\MailgunApiWrapper;
use ByJG\Mail\Wrapper\FakeSenderWrapper;
use ByJG\Util\JinjaPhp\Loader\FileSystemLoader;
use ByJG\Mail\Envelope;

return [
    MailWrapperInterface::class => DI::bind(MailWrapperInterface::class)
        ->withFactoryMethod('create', [Param::get('EMAIL_CONNECTION')])  // or custom factory
        ->toSingleton(),
];
```

The actual factory function in the project registers the wrappers and calls
`MailerFactory::create($connectionString)` — the scheme in the URI selects the wrapper
(`tls://` → `PHPMailerWrapper`, `mailgun://` → `MailgunApiWrapper`, `fakesender://` →
`FakeSenderWrapper`).

---

## Testing emails

In the test environment `EMAIL_CONNECTION=fakesender://nothing` means every `send()` call
succeeds immediately without touching a mail server. No extra test setup needed.

```php
class WelcomeTest extends BaseApiTestCase
{
    public function testWelcomeEmailIsSent(): void
    {
        // Trigger the endpoint that sends a welcome email
        $body = $this->sendRequest(
            (new FakeApiRequester())
                ->withMethod('POST')->withPath('/register')
                ->withRequestBody(json_encode(['email' => 'jane@example.com']))
                ->expectStatus(200)
        );

        // The mailer ran with FakeSenderWrapper — assert on the response, not the email
        $result = json_decode($body->getBody()->getContents(), true);
        $this->assertNotEmpty($result['id']);
    }
}
```

If you need to assert the email content itself, `FakeSenderWrapper` does not capture
envelopes (its `send()` just returns success) — subclass it in your test:

```php
use ByJG\Mail\Envelope;
use ByJG\Mail\SendResult;
use ByJG\Mail\Wrapper\FakeSenderWrapper;

class CapturingSenderWrapper extends FakeSenderWrapper
{
    public ?Envelope $lastEnvelope = null;

    public function send(Envelope $envelope): SendResult
    {
        $this->lastEnvelope = $envelope;
        return parent::send($envelope);
    }
}

$fake = new CapturingSenderWrapper();
// Bind it manually for the test and call the code under test...
$this->assertStringContainsString('Jane', $fake->lastEnvelope->getBody());
$this->assertEquals('jane@example.com', $fake->lastEnvelope->getTo()[0]);
```

---

## Quick reference

| Goal | Code |
|---|---|
| Send with template | `Config::get('MAIL_ENVELOPE', [$to, $subject, 'tpl', $vars])` then `$mailer->send($envelope)` |
| Send plain HTML | `new Envelope($from, $to, $subject, $html)` then `$mailer->send()` |
| Add CC / BCC | `$envelope->addCC($email)` / `$envelope->addBCC($email)` |
| Attach a file | `$envelope->addAttachment($name, $path, $mime)` |
| Embed an image | `$envelope->addEmbedImage($cid, $path, $mime)` — reference as `cid:$cid` in body |
| Switch provider | Change `EMAIL_CONNECTION` URI in `credentials.env` |
| Disable in tests | `EMAIL_CONNECTION=fakesender://nothing` (already set in test config) |