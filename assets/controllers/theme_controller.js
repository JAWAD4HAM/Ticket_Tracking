import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.apply();
    }

    apply(event) {
        const theme = event?.target?.value ?? this.element.value;
        if (!theme) {
            return;
        }
        document.documentElement.dataset.theme = theme;
    }
}
