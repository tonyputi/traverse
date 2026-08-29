# Browser fixtures

These fixtures are the sole source of browser integration content in CI. Tests must never depend on arbitrary public websites.

- `plain.html` contains ordinary semantic HTML.
- `javascript-rendered.html` replaces its initial loading state after `DOMContentLoaded`.
- `links.html` contains same-page, relative, and absolute links.
- `forms.html` contains common labelled interactive controls.
- `structured-data.html` contains visible article content and JSON-LD for an `Article`.

Issue #3 will add a local serving mechanism only if Lightpanda requires an HTTP origin. These files intentionally contain no driver-specific test infrastructure.
