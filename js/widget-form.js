/* global wp, console */
/* eslint-disable strict */
/* eslint consistent-this: [ "error", "form" ] */
/* eslint-disable complexity */

if ( ! wp.widgets ) {
	wp.widgets = {};
}
if ( ! wp.widgets.formConstructor ) {
	wp.widgets.formConstructor = {};
}

wp.widgets.Form = (function( api, $ ) {
	'use strict';

	/**
	 * Customize Widget Form.
	 *
	 * @constructor
	 */
	return api.Class.extend({

		/**
		 * Form config.
		 *
		 * @var object
		 */
		config: {},

		/**
		 * Initialize.
		 *
		 * @param {object}             properties           Properties.
		 * @param {string}             properties.id_base   The widget ID base (aka type).
		 * @param {wp.customize.Value} properties.model     The Value or Setting instance containing the widget instance data object.
		 * @param {Element|jQuery}     properties.container The Value or Setting instance containing the widget instance data object.
		 * @param {object}             properties.config    Form config.
		 * @return {void}
		 */
		initialize: function initialize( properties ) {
			var form = this, args, previousValidate;

			args = _.extend(
				{
					model: null,
					container: null,
					config: ! _.isEmpty( form.config ) ? _.clone( form.config ) : {
						form_template_id: '',
						notifications_template_id: '',
						l10n: {},
						default_instance: {}
					}
				},
				properties ? _.clone( properties ) : {}
			);

			if ( ! args.model || ! args.model.extended || ! args.model.extended( api.Value ) ) {
				throw new Error( 'Missing model property which must be a Value or Setting instance.' );
			}

			_.extend( form, args );
			form.setting = args.model; // @todo Deprecate 'setting' name in favor of 'model'?

			if ( form.model.notifications ) {
				form.notifications = form.model.notifications;
			} else {
				form.notifications = new api.Values({ defaultConstructor: api.Notification });
			}
			form.renderNotifications = _.bind( form.renderNotifications, form );

			form.container = $( form.container );
			if ( 0 === form.container.length ) {
				throw new Error( 'Missing container property as Element or jQuery.' );
			}

			previousValidate = form.model.validate;

			/**
			 * Validate the instance data.
			 *
			 * @todo In order for returning an error/notification to work properly, api._handleSettingValidities needs to only remove notification errors that are no longer valid which are fromServer:
			 *
			 * @param {object} value Instance value.
			 * @returns {object|Error|wp.customize.Notification} Sanitized instance value or error/notification.
			 */
			form.model.validate = function validate( value ) {
				var setting = this, newValue, oldValue, error, code, notification; // eslint-disable-line consistent-this
				newValue = _.extend( {}, form.config.default_instance, value );
				oldValue = _.extend( {}, setting() );

				newValue = previousValidate.call( setting, newValue );

				newValue = form.sanitize( newValue, oldValue );
				if ( newValue instanceof Error ) {
					error = newValue;
					code = 'invalidValue';
					notification = new api.Notification( code, {
						message: error.message,
						type: 'error'
					} );
				} else if ( newValue instanceof api.Notification ) {
					notification = newValue;
				}

				// If sanitize method returned an error/notification, block setting u0date.
				if ( notification ) {
					newValue = null;
				}

				// Remove all existing notifications added via sanitization since only one can be returned.
				form.notifications.each( function iterateNotifications( iteratedNotification ) {
					if ( iteratedNotification.viaWidgetFormSanitizeReturn && ( ! notification || notification.code !== iteratedNotification.code ) ) {
						form.notifications.remove( iteratedNotification.code );
					}
				} );

				// Add the new notification.
				if ( notification ) {
					notification.viaWidgetFormSanitizeReturn = true;
					form.notifications.add( notification.code, notification );
				}

				return newValue;
			};
		},

		/**
		 * Render notifications.
		 *
		 * Renders the `form.notifications` into the control's container.
		 * Control subclasses may override this method to do their own handling
		 * of rendering notifications.
		 *
		 * Note that this debounced/deferred rendering is needed for two reasons:
		 * 1) The 'remove' event is triggered just _before_ the notification is actually removed.
		 * 2) Improve performance when adding/removing multiple notifications at a time.
		 *
		 * @returns {void}
		 */
		renderNotifications: _.debounce( function renderNotifications() {
			var form = this, container, notifications, hasError = false;
			container = form.getNotificationsContainerElement();
			if ( ! container || ! container.length ) {
				return;
			}
			notifications = [];
			form.notifications.each( function( notification ) {
				notifications.push( notification );
				if ( 'error' === notification.type ) {
					hasError = true;
				}

				if ( ! notification.hasA11ySpoken ) {

					// @todo In the context of the Customizer, this presently will end up getting spoken twice due to wp.customize.Control also rendering it.
					wp.a11y.speak( notification.message, 'assertive' );
					notification.hasA11ySpoken = true;
				}
			} );

			if ( 0 === notifications.length ) {
				container.stop().slideUp( 'fast' );
			} else {
				container.stop().slideDown( 'fast', null, function() {
					$( this ).css( 'height', 'auto' );
				} );
			}

			if ( ! form._notificationsTemplate ) {
				form._notificationsTemplate = wp.template( form.config.notifications_template_id );
			}

			form.container.toggleClass( 'has-notifications', 0 !== notifications.length );
			form.container.toggleClass( 'has-error', hasError );
			container.empty().append( $.trim(
				form._notificationsTemplate( { notifications: notifications, altNotice: Boolean( form.altNotice ) } )
			) );
		} ),

		/**
		 * Get the element inside of a form's container that contains the notifications.
		 *
		 * Control subclasses may override this to return the proper container to render notifications into.
		 *
		 * @returns {jQuery} Notifications container element.
		 */
		getNotificationsContainerElement: function getNotificationsContainerElement() {
			var form = this;
			return form.container.find( '.js-widget-form-notifications-container:first' );
		},

		/**
		 * Sanitize the instance data.
		 *
		 * @param {object} newInstance New instance.
		 * @param {object} oldInstance Existing instance.
		 * @returns {object|Error|wp.customize.Notification} Sanitized instance or validation error/notification.
		 */
		sanitize: function sanitize( newInstance, oldInstance ) {
			var form = this, instance, code, notification;
			if ( _.isUndefined( oldInstance ) ) {
				throw new Error( 'Expected oldInstance' );
			}
			instance = _.extend( {}, form.config.default_instance, newInstance );

			if ( ! instance.title ) {
				instance.title = '';
			}

			// Warn about markup in title.
			code = 'markupTitleInvalid';
			if ( /<\/?\w+[^>]*>/.test( instance.title ) ) {
				notification = new api.Notification( code, {
					message: form.config.l10n.title_tags_invalid,
					type: 'warning'
				} );
				form.notifications.add( code, notification );
			} else {
				form.notifications.remove( code );
			}

			/*
			 * Trim per sanitize_text_field().
			 * Protip: This prevents the widget partial from refreshing after adding a space or adding a new paragraph.
			 */
			instance.title = $.trim( instance.title );

			return instance;
		},

		/**
		 * Get cloned value.
		 *
		 * @todo This will only do shallow copy.
		 *
		 * @return {object} Instance.
		 */
		getValue: function getValue() {
			var form = this;
			return _.extend(
				{},
				form.config.default_instance,
				form.model() || {}
			);
		},

		/**
		 * Merge the props into the current value.
		 *
		 * @todo Rename this update? Rename this set? Or setExtend? Or setValue()?
		 *
		 * @param {object} props Instance props.
		 * @returns {void}
		 */
		setState: function setState( props ) {
			var form = this, value;
			value = _.extend( form.getValue(), props || {} );
			form.model.set( value );
		},

		/**
		 * Create synced property value.
		 *
		 * Given that the current setting contains an object value, create a new
		 * model (Value) to represent the value of one of its properties, and
		 * sync the value between the root object and the property value when
		 * either are changed. The returned Value can be used to sync with an
		 * Element.
		 *
		 * @param {wp.customize.Value} root Root value instance.
		 * @param {string} property Property name.
		 * @returns {object} Property value instance.
		 */
		createSyncedPropertyValue: function createSyncedPropertyValue( root, property ) {
			var propertyValue, rootChangeListener, propertyChangeListener;

			propertyValue = new api.Value( root.get()[ property ] );

			// Sync changes to the property back to the root value.
			propertyChangeListener = function( newPropertyValue ) {
				var rootValue = _.clone( root.get() );
				rootValue[ property ] = newPropertyValue;
				root.set( rootValue );
			};
			propertyValue.bind( propertyChangeListener );

			// Sync changes in the root value to the model.
			rootChangeListener = function updateRootValue( newRootValue, oldRootValue ) {
				if ( ! _.isEqual( newRootValue[ property ], oldRootValue[ property ] ) ) {
					propertyValue.set( newRootValue[ property ] );
				}
			};
			root.bind( rootChangeListener );

			return {
				value: propertyValue,
				propertyChangeListener: propertyChangeListener,
				rootChangeListener: rootChangeListener
			};
		},

		/**
		 * Create elements to link setting value properties with corresponding inputs in the form.
		 *
		 * @returns {void}
		 */
		linkPropertyElements: function linkPropertyElements() {
			var form = this, initialInstanceData;
			initialInstanceData = form.getValue();
			form.syncedProperties = {};
			form.container.find( ':input[data-field]' ).each( function() {
				var input = $( this ), field = input.data( 'field' ), syncedProperty;
				if ( _.isUndefined( initialInstanceData[ field ] ) ) {
					return;
				}

				syncedProperty = form.createSyncedPropertyValue( form.model, field );
				syncedProperty.element = new api.Element( input );
				syncedProperty.element.set( initialInstanceData[ field ] );
				syncedProperty.element.sync( syncedProperty.value );
				form.syncedProperties[ field ] = syncedProperty;
			} );
		},

		/**
		 * Unlink setting value properties with corresponding inputs in the form.
		 *
		 * @returns {void}
		 */
		unlinkPropertyElements: function unlinkPropertyElements() {
			var form = this;
			_.each( form.syncedProperties, function( syncedProperty ) {
				syncedProperty.element.unsync( syncedProperty.value );
				form.model.unbind( syncedProperty.rootChangeListener );
				syncedProperty.value.callbacks.remove();
			} );
			form.syncedProperties = {};
		},

		/**
		 * Get template function.
		 *
		 * @returns {Function} Template function.
		 */
		getTemplate: function getTemplate() {
			var form = this;
			if ( ! form._template ) {
				if ( ! $( '#tmpl-' + form.config.form_template_id ).is( 'script[type="text/template"]' ) ) {
					throw new Error( 'Missing script[type="text/template"]#' + form.config.form_template_id + ' script for widget form.' );
				}
				form._template = wp.template( form.config.form_template_id );
			}
			return form._template;
		},

		/**
		 * Embed.
		 *
		 * @deprecated
		 * @returns {void}
		 */
		embed: function embed() {
			if ( 'undefined' !== typeof console ) {
				console.warn( 'wp.widgets.Form#embed is deprecated.' );
			}
			this.render();
		},

		/**
		 * Render (mount) the form into the container.
		 *
		 * @returns {void}
		 */
		render: function render() {
			var form = this, template = form.getTemplate();
			form.container.html( template( form ) );
			form.linkPropertyElements();
			form.notifications.bind( 'add', form.renderNotifications );
			form.notifications.bind( 'remove', form.renderNotifications );
		},

		/**
		 * Destruct (unrender/unmount) the form.
		 *
		 * Subclasses can do cleanup of event listeners on other components,
		 *
		 * @returns {void}
		 */
		destruct: function destruct() {
			var form = this;
			form.container.empty();
			form.unlinkPropertyElements();
			form.notifications.unbind( 'add', form.renderNotifications );
			form.notifications.unbind( 'remove', form.renderNotifications );
		}
	});

} )( wp.customize, jQuery );
