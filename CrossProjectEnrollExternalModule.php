<?php
namespace Vanderbilt\CrossProjectEnrollExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class CrossProjectEnrollExternalModule extends AbstractExternalModule
{
	public $modSettings;

	function __construct() {
		parent::__construct();
		if(!defined("PROJECT_ID")) {
			return;
		}
		$this->modSettings = ExternalModules::getProjectSettingsAsArray([$this->PREFIX], PROJECT_ID);
		if(!isset($this->modSettings['satellite-selection-field']['value']) || empty($this->modSettings['satellite-selection-field']['value'])) {
			return;
		}
	}
	
	function redcap_every_page_top($project_id) {
		if(empty($project_id)) {
			return;
		} else {
			if(PAGE == "DataEntry/record_home.php") {
				$this->addEnrollOptions($project_id, $_GET['id']);
			}
		}
	}
	
	function hook_data_entry_form_top($project_id, $record, $instrument, $event_id) {
		$this->addEnrollOptions($project_id, $record);
	}

	/**
	 * Creates an array containing all project IDs which have been checked in the satellite selection field.
	 */
	private function getEnrollPIDs($project_id, $record) {
		$thisjson = \REDCap::getData($project_id, 'json', $record, $this->modSettings['satellite-selection-field']['value'], $this->getFirstEventId($project_id));
		$thisdata = json_decode($thisjson, true);
		$projIDs = array();
		if(isset($thisdata[0]) && is_array($thisdata[0])) {
			foreach ($thisdata[0] AS $k => $v) {
				if($v == 1) {
					$projIDs[] = str_replace($this->modSettings['satellite-selection-field']['value'].'___', '' , $k);
				}
			}
		}
		return $projIDs;
	}

	/**
	 * Process record and create array of data on projects that have been checked in satellite selection field
	 */
	private function getProjectsInfo($project_id, $record) {
		$projIDs = $this->getEnrollPIDs($project_id, $record);
		$projInfo = array();
		$curProjRights = \UserRights::getPrivileges($project_id);
		if(!empty($projIDs) && !empty($curProjRights[$project_id])) {
			foreach($projIDs AS $k => $v) {
				$satProjRights = \UserRights::getPrivileges($v, key($curProjRights[$project_id]));
				if(!empty($satProjRights[$v][key($curProjRights[$project_id])])) {
					$satProjObj = new \Project($v);
					if(!empty($satProjObj->project)) {
						$projInfo[$v]['name'] = $satProjObj->project['app_title'];
						
						$thisjson = \REDCap::getData($v, 'json', $record, 'record_id');
						$thisdata = json_decode($thisjson, true);
						
						$projInfo[$v]['enrolled'] = (empty($thisdata) ? 0 : 1 );
					}
				}
			}
		}
		return $projInfo;
	}

	/**
	 * Add enroll buttons (or view buttons if the record has already been enrolled) for applicable satellite projects.
	 */
	public function addEnrollOptions($project_id, $record) {
		$projInfo = $this->getProjectsInfo($project_id, $record);
		echo $this->getEnrollJS($projInfo, $project_id, $record);
	}

	/**
	 * Return JS to add enroll/view buttons to top of form.
	 */
	private function getEnrollJS($projInfo = array(), $project_id, $record) {
		list($prefix, $version) = ExternalModules::getParseModuleDirectoryPrefixAndVersion($this->getModuleDirectoryName());
		$url = ExternalModules::getUrl($prefix, "enroll_record.php");
		ob_start();
		if(!empty($projInfo) && is_array($projInfo)):
		?>
			<script type='text/javascript'>
				$(document).ready(function(){
					var curRecord = <?php echo $record; ?>;
					var htmlToAppend = '';
					var enrollLinks = '';
					var viewLinks = '';
					var url = '<?php echo $url; ?>';

					<?php foreach($projInfo AS $projID => $projData): ?>
						<?php if($projData['enrolled'] == 1): ?>
							viewLinks += '<a href="<?php echo APP_PATH_WEBROOT."DataEntry/record_home.php?pid=".$projID."&arm=1&id=".$record; ?>"><button><?php echo $projData['name']; ?></button></a>';
						<?php else: ?>
							enrollLinks += '<button class="cPEnrollBtn" data-satellite-pid="<?php echo $projID; ?>"><?php echo $projData['name']; ?></button>';
						<?php endif; ?>
					<?php endforeach; ?>

					if(enrollLinks.length) {
						htmlToAppend += '<h4 style="border-bottom: 1px solid #d0d0d0">Enroll:</h4>';
						htmlToAppend += enrollLinks;
					}
					if(viewLinks.length) {
						htmlToAppend += '<h4 style="border-bottom: 1px solid #d0d0d0">View:</h4>';
						htmlToAppend += viewLinks;
					}
					if(htmlToAppend.length) {
						htmlToAppend = '<div style="padding-bottom: 20px; max-width: 800px;">'+htmlToAppend+'</div>';
					}

					if($('#record_display_name').length) {
						$('#record_display_name').before(htmlToAppend);
					} else {
						$('#form').before(htmlToAppend);
					}

					$('.cPEnrollBtn').click(function(evt){
						evt.preventDefault();
						if(confirm('Are you sure you want to enroll this patient in '+$(this).text()+'?')) {
							var satellitePid = $(this).attr('data-satellite-pid');
							$.post(url, { satellite_pid: satellitePid, unique_id: curRecord, unique_field: 'record_id'}, function(data) {
								if(data.status) {
									<?php if(!empty($this->modSettings['enroll-destination']['value'])): ?>
										window.location.href = "<?php echo APP_PATH_WEBROOT; ?>DataEntry/record_home.php?pid="+data.pid+"&arm=1&id="+data.record_id;
									<?php else: ?>
										window.location.href = "<?php echo APP_PATH_WEBROOT; ?>DataEntry/index.php?pid="+data.pid+"&id="+data.record_id+"&event_id="+data.first_event+"&page="+data.first_inst;
									<?php endif; ?>
								} else {
									alert('There was a problem enrolling this record.');
								}
							});
						}
					});
				});
			</script>
		<?php
		endif;
		$output = ob_get_contents();
		ob_clean();
		return $output;
	}
}
