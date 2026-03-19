<?php namespace Vanderbilt\AddressExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

include_once GeoBoundaryChecker.php;

class AddressExternalModule extends AbstractExternalModule
{
	function hook_survey_page($project_id, $record, $instrument, $event_id, $group_id) {
		$this->addAddressAutoCompletion($project_id, $record, $instrument, $event_id, $group_id);
	}

	function hook_data_entry_form($project_id, $record, $instrument, $event_id, $group_id) {
		$this->addAddressAutoCompletion($project_id, $record, $instrument, $event_id, $group_id);
	}

	function addAddressAutoCompletion($project_id, $record, $instrument, $event_id, $group_id) {
		$key = $this->getProjectSetting('google-api-key',$project_id);
		$autocomplete = $this->getProjectSetting('autocomplete',$project_id);
		$streetNumber = $this->getProjectSetting('street-number',$project_id);
		$street = $this->getProjectSetting('street',$project_id);
		$city = $this->getProjectSetting('city',$project_id);
		$county = $this->getProjectSetting('county',$project_id);
		$state = $this->getProjectSetting('state',$project_id);
		$zip = $this->getProjectSetting('zip',$project_id);
		$country = $this->getProjectSetting('country',$project_id);
		$latitude = $this->getProjectSetting('latitude',$project_id);
		$longitude = $this->getProjectSetting('longitude',$project_id);
		$withincity = $this->getProjectSetting('within_city',$project_id);
		$neighbourhood = $this->getProjectSetting('within_neighbourhood',$project_id);
		$import = $this->getProjectSetting('import-google-api',$project_id);

		$checker = new GeoBoundaryChecker(
			$this->getProjectSetting('city_boundary'),
			$this->getProjectSetting('neighbourhood_boundaries'),
			$this->getProjectSetting('approved_neighbourhoods')
		);

		$data = $checker->exportForClient();

		if ($key && $autocomplete) {
			?>
			<script>
				var cityPolygon = <?php echo json_encode($data['cityPolygon']); ?>;
				var neighbourhoods = <?php echo json_encode($data['neighbourhoods']); ?>;
				var approvedNeighbourhoods = <?php echo json_encode($data['approved']); ?>;

				function isPointInPolygon(point, polygon) {
					var x = point[0], y = point[1];
					var inside = false;
					for (var i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
						var xi = polygon[i][0], yi = polygon[i][1];
						var xj = polygon[j][0], yj = polygon[j][1];

						var intersect = ((yi > y) != (yj > y)) &&
							(x < (xj - xi) * (y - yi) / (yj - yi) + xi);
						if (intersect) inside = !inside;
					}
					return inside;
				}

				function pointInBBox(point, bbox) {
					var minX = bbox[0], maxX = bbox[1], minY = bbox[2], maxY = bbox[3];
					var x = point[1], y = point[0];
					return !(x < minX || x > maxX || y < minY || y > maxY);
				}

				function checkPoint(point) {
					if (!isPointInPolygon(point, cityPolygon)) {
						return { city: 0, neighbourhood: null };
					}

					for (var i = 0; i < neighbourhoods.length; i++) {
						var n = neighbourhoods[i];

						if (!pointInBBox(point, n.bbox)) continue;

						if (isPointInPolygon(point, n.polygon)) {
							return { city: 1, neighbourhood: n.name };
						}
					}

					return { city: 1, neighbourhood: null };
				}

				var autocompletePrefix = 'googleSearch_';
				var autocompleteId = autocompletePrefix+'autocomplete';

				$(document).ready(function() {
					<?php $numFields = 0; ?>

					<?php if ($streetNumber): ?>
						$('[name="<?php echo $streetNumber; ?>"]').attr('id', autocompletePrefix+'street_number').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($street): ?>
						$('[name="<?php echo $street; ?>"]').attr('id', autocompletePrefix+'route').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($city): ?>
						$('[name="<?php echo $city; ?>"]').attr('id', autocompletePrefix+'locality').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($county): ?>
						$('[name="<?php echo $county; ?>"]').attr('id', autocompletePrefix+'administrative_area_level_2').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($state): ?>
						$('[name="<?php echo $state; ?>"]').attr('id', autocompletePrefix+'administrative_area_level_1').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($zip): ?>
						$('[name="<?php echo $zip; ?>"]').attr('id', autocompletePrefix+'postal_code').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($country): ?>
						$('[name="<?php echo $country; ?>"]').attr('id', autocompletePrefix+'country').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($latitude): ?>
						$('[name="<?php echo $latitude; ?>"]').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($longitude): ?>
						$('[name="<?php echo $longitude; ?>"]').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($withincity): ?>
						$('[name="<?php echo $withincity; ?>"]').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($neighbourhood): ?>
						$('[name="<?php echo $neighbourhood; ?>"]').prop('disabled', true);
						<?php $numFields++; ?>
					<?php endif; ?>

					<?php if ($numFields > 0): ?>
						$('[name="<?php echo $autocomplete; ?>"]')
							.attr('id', autocompleteId)
							.wrap('<div id="locationField"></div>')
							.attr('placeholder', 'Enter your address here')
							.on('keydown focus', function() { geolocate(); });
					<?php endif; ?>

					initAutocomplete();
				});
			</script>

			<script>
				var placeSearch, autocomplete;
				var componentForm = {
					<?php echo ($streetNumber ? "street_number: 'short_name'," : "" ); ?>
					<?php echo ($street ? "route: 'long_name'," : "" ); ?>
					<?php echo ($city ? "locality: 'long_name'," : "" ); ?>
					<?php echo ($county ? "administrative_area_level_2: 'short_name'," : "" ); ?>
					<?php echo ($state ? "administrative_area_level_1: 'short_name'," : "" ); ?>
					<?php echo ($country ? "country: 'long_name'," : "" ); ?>
					<?php echo ($zip ? "postal_code: 'short_name'," : "" ); ?>
				};

				function initAutocomplete() {
					autocomplete = new google.maps.places.Autocomplete(
						document.getElementById(autocompleteId),
						{types: ['address']}
					);

					autocomplete.addListener('place_changed', fillInAddress);

					let addressField = document.getElementById(autocompleteId);
					addressField.addEventListener('change', function() {
						if (addressField.value === "") fillInAddress();
					});
				}

				function updateValue(id, value){
					var element;

					if(id == 'latitude') {
						element = $('[name="<?php echo $latitude; ?>"]');
					}
					else if(id == 'longitude') {
						element = $('[name="<?php echo $longitude; ?>"]');
					}
					else {
						element = $('#' + id);
					}

					if(!element.length) return;

					element.val(value).change();
				}

				function fillInAddress() {
					$('#'+autocompleteId).change();

					var place = autocomplete.getPlace();

					for (var component in componentForm) {
						updateValue(autocompletePrefix+component, '');
					}

					if (place !== undefined && place.geometry) {

						<?php
							echo ($latitude ? "updateValue('latitude',place.geometry.location.lat());\n" : "");
							echo ($longitude ? "updateValue('longitude',place.geometry.location.lng());\n" : "");
						?>

						var lat = place.geometry.location.lat();
						var lng = place.geometry.location.lng();
						var point = [lat, lng];

						var result = checkPoint(point);

						updateValue('<?php echo $withincity; ?>', result.city ? '1' : '0');
						updateValue('<?php echo $neighbourhood; ?>', result.neighbourhood || '');

						for (var i = 0; i < place.address_components.length; i++) {
							var addressType = place.address_components[i].types[0];
							if (componentForm[addressType] && document.getElementById(autocompletePrefix+addressType)) {
								var val = place.address_components[i][componentForm[addressType]];
								if(addressType == 'administrative_area_level_2') {
									val = $.trim(val.replace('County',''));
								}
								updateValue(autocompletePrefix+addressType, val);
								document.getElementById(autocompletePrefix+addressType).disabled = false;
							}
						}
					} else {
						<?php
							echo ($latitude ? "updateValue('latitude','');\n" : "");
							echo ($longitude ? "updateValue('longitude','');\n" : "");
						?>
					}

					doBranching();
				}

				function geolocate() {
					if (navigator.geolocation) {
						navigator.geolocation.getCurrentPosition(function(position) {
							var geolocation = {
								lat: position.coords.latitude,
								lng: position.coords.longitude
							};
							var circle = new google.maps.Circle({
								center: geolocation,
								radius: position.coords.accuracy
							});
							autocomplete.setBounds(circle.getBounds());
						});
					}
				}
			</script>

			<?php
			if ($import) {
				echo "<script src=\"https://maps.googleapis.com/maps/api/js?key=".$key."&libraries=places\"></script>";
			}
		}
	}
}
