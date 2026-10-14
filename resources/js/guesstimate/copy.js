/*--- GUESSTIMATE: KOPIOWANIE LINKU ---*/

export default (text) => ({
  copied: false,
  async copy() {
    try {
      await navigator.clipboard.writeText(text);
    } catch (error) {
      const input = document.createElement('textarea');
      input.value = text;
      document.body.appendChild(input);
      input.select();
      document.execCommand('copy');
      input.remove();
    }
    this.copied = true;
    setTimeout(() => { this.copied = false; }, 2000);
  },
});
