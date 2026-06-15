import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import './style.scss';

export function edit() {
	const blockProps = useBlockProps();
	
	return (
		<div {...blockProps}>
			<div className="dogpark-suggestion-form">
				<h3>{__('Προτείνετε ένα πάρκο ή βελτιώσεις', 'dogpark')}</h3>
				<p>{__('Συμπληρώστε τη φόρμα για να προτείνετε ένα νέο πάρκο ή βελτιώσεις σε υπάρχον.', 'dogpark')}</p>
				<div className="dogpark-placeholder">
					{__('Dog Park Suggestion Form', 'dogpark')}
				</div>
			</div>
		</div>
	);
}

export function save() {
	return null; // Server-side rendering
}