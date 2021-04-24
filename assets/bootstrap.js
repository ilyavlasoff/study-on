import { startStimulusApp } from '@symfony/stimulus-bridge';

import Util from 'bootstrap/js/src/util';
global.Util = Util;
import 'bootstrap/js/dist/modal';

// Registers Stimulus controllers from controllers.json and in the controllers/ directory
export const app = startStimulusApp(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.(j|t)sx?$/
));
