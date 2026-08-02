# Changelog

## 1.0.2

- Fixed PAY by square QR codes embedding the current date by default. The date field is now left empty when no `date` is provided, so banks treat the payment as payable immediately (current date at scan time) and no longer show a "date in the past" warning when the code is scanned later.

## 1.0.1

- Fixed QR caption layout in BACS emails by adding inline styles
- Removed `render_bacs_qr_styles()` and associated CSS classes — consistent rendering is now entirely handled by inline styles, so the thankyou page and emails always look the same
- Removed unused `render_order_qr_on_thankyou()` function and its global wrapper
