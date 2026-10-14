/*--- GUESSTIMATE: PODGLĄD KALKULACJI NA ŻYWO ---*/
// Kalkulacja zawsze liczona jest po stronie serwera; przeglądarka tylko wyświetla wynik.

export default (url) => ({
  loading: false,
  failed: false,
  dirty: false,
  timer: null,

  schedule() {
    this.dirty = true;
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.refresh(), 400);
  },

  async refresh() {
    this.loading = true;
    this.failed = false;

    try {
      const form = new FormData(this.$root);
      form.delete('action');
      const response = await fetch(url, {
        method: 'POST',
        body: form,
        credentials: 'same-origin',
        headers: { 'X-GST-Nonce': document.querySelector('meta[name="gst-nonce"]')?.content || '' },
      });

      if (!response.ok) throw new Error(String(response.status));
      this.$refs.preview.innerHTML = await response.text();
    } catch (error) {
      this.failed = true;
    } finally {
      this.loading = false;
    }
  },
});
