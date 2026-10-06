import { restTimer } from './rest-timer';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('restTimer', restTimer);
});
