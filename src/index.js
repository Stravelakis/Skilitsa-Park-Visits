/**
 * Build entry point for all blocks
 * This file is compiled by @wordpress/scripts to build/index.js
 * The compiled output is enqueued as the editor script for all blocks
 */

import { registerBlockType } from '@wordpress/blocks';

// Import block components
import { edit as dogParkBestHourEdit, save as dogParkBestHourSave } from '../blocks/dog-park-best-hour/edit';
import { edit as visitorFormEdit, save as visitorFormSave } from '../blocks/visitor-form/edit';

// Register Dog Park Best Hour block
registerBlockType('dog-park/best-hour', {
	edit: dogParkBestHourEdit,
	save: dogParkBestHourSave,
});

// Register Visitor Form block
registerBlockType('dog-park/suggestion-form', {
	edit: visitorFormEdit,
	save: visitorFormSave,
});