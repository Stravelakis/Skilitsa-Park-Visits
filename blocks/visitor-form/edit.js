import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import './style.scss';

registerBlockType('dog-park/suggestion-form', {
    edit() {
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
    },
    
    save() {
        return null; // Server-side rendering
    }
});