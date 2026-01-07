import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        delay: { type: Number, default: 5000 }
    };

    connect() {
        this.timeout = setTimeout(() => {
            this.element.style.opacity = '0';
            setTimeout(() => this.element.remove(), 500);
        }, this.delayValue);
    }

    disconnect() {
        if (this.timeout) {
            clearTimeout(this.timeout);
        }
    }
}
