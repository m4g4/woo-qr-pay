# Changelog

## 1.0.1

- Fixed QR caption layout in BACS emails by adding inline styles
- Removed `render_bacs_qr_styles()` and associated CSS classes — consistent rendering is now entirely handled by inline styles, so the thankyou page and emails always look the same
- Removed unused `render_order_qr_on_thankyou()` function and its global wrapper
