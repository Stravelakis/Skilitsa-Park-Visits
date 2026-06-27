import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import './style.scss';

export function edit({ attributes, setAttributes }) {
	const { parkId, isDarkMode } = attributes;
	const [parks, setParks] = useState([]);
	const blockProps = useBlockProps();

	// Fetch parks from REST API
	useEffect(() => {
		wp.apiFetch({ path: '/dog-park/v1/parks-list' }).then((data) => {
			setParks([{ id: 0, name: __('Select a park', 'dogpark') }, ...data]);
		});
	}, []);

	return (
		<div {...blockProps} className={`dogpark-block ${isDarkMode ? 'dark-mode' : ''}`}>
			<InspectorControls>
				<PanelBody title={__('Settings', 'dogpark')}>
					<SelectControl
						label={__('Select Park', 'dogpark')}
						value={parkId}
						options={parks.map(park => ({ label: park.name, value: park.id }))}
						onChange={(value) => setAttributes({ parkId: value })}
					/>
					<SelectControl
						label={__('Mode', 'dogpark')}
						value={isDarkMode ? 'dark' : 'light'}
						options={[ 
							{ label: __('Light', 'dogpark'), value: 'light' }, 
							{ label: __('Dark', 'dogpark'), value: 'dark' }
						]}
						onChange={(value) => setAttributes({ isDarkMode: value === 'dark' })}
					/>
				</PanelBody>
			</InspectorControls>
			
			<div className="dogpark-preview">
				{parkId === 0 && (
					<p>{__('Select a park to see the best hour for your σκυλίτσα!', 'dogpark')}</p>
				)}
				{parkId !== 0 && (
					<p>{__('Loading...', 'dogpark')}</p>
				)}
			</div>
		</div>
	);
}

export function save() {
	return null; // Server-side rendering
}