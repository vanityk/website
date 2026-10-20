// js/weather.js
class WeatherWidget {
  constructor(options = {}) {
    this.endpoint = options.endpoint || '/api/weather.php';
    this.descEl = document.getElementById(options.descId || 'wt-desc');
    this.tempEl = document.getElementById(options.tempId || 'wt-temp');
    this.rainEl = document.getElementById(options.rainId || 'wt-rain');
  }

  async init() {
    try {
      const res = await fetch(this.endpoint);
      if (!res.ok) throw new Error('Network response was not ok');
      const data = await res.json();

      if (data.status === 'success') {
        this.render(data);
      } else {
        this.renderError();
      }
    } catch (err) {
      console.error('Weather load failed:', err);
      this.renderError();
    }
  }

  render(data) {
    if (this.descEl) this.descEl.textContent = data.weather;
    if (this.tempEl) this.tempEl.textContent = data.temp;
    if (this.rainEl) this.rainEl.textContent = `💧 ${data.rain}`;
  }

  renderError() {
    if (this.descEl) this.descEl.textContent = '暫無氣象';
  }
}

// 監聽 DOM 載入完成自動執行
document.addEventListener('DOMContentLoaded', () => {
  const weather = new WeatherWidget();
  weather.init();
});