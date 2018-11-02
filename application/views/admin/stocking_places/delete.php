<div class="container">
  <h4 class="xs-right">
    <div class="row">
      <?php if(isset($stocking_place_id)) { 
        foreach($stocking_places as $stocking_place) { ?>
      <a href="<?php echo $stocking_place->stocking_place_id; ?>" class=<?php if ($stocking_place_id == $stocking_place->stocking_place_id) {echo "tab_selected" ;}else{echo "tab_unselected";} ?>>
        <?php echo $stocking_place->name; ?>
      </a>
      <?php } ?>
    </div>
    <div class="row" style="margin-top: 5px;">
      <a href="<?php echo base_url(); ?>admin/modify_stocking_place/<?php echo $stocking_place_id; ?>" class="tab_unselected"><?php echo $this->lang->line('admin_modify'); ?></a>
      <a href="#" class="tab_selected"><?php echo $this->lang->line('admin_delete'); ?></a>
      <a href="<?php echo base_url(); ?>admin/new_stocking_place/" class="tab_unselected"><?php echo $this->lang->line('admin_add'); ?></a>
    <?php } ?>
    </div>
    <div class="row" style="margin-top: 5px;">
      <a href="<?php echo base_url(); ?>admin/view_users" class="tab_unselected"><?php echo $this->lang->line('admin_tab_users'); ?></a>
      <a href="<?php echo base_url(); ?>admin/view_tags" class="tab_unselected"><?php echo $this->lang->line('admin_tab_tags'); ?></a>
      <a href="<?php echo base_url(); ?>admin/view_stocking_places" class="tab_unselected"><?php echo $this->lang->line('admin_tab_stocking_places'); ?></a>
      <a href="<?php echo base_url(); ?>admin/view_suppliers" class="tab_selected"><?php echo $this->lang->line('admin_tab_suppliers'); ?></a>
      <a href="<?php echo base_url(); ?>admin/view_item_groups" class="tab_unselected"><?php echo $this->lang->line('admin_tab_item_groups'); ?></a>
      <a href="<?php echo base_url(); ?>admin/" class="tab_unselected"><?php echo $this->lang->line('admin_tab_admin'); ?></a>
    </div>
  </h4>

  <?php if(isset($short) && isset($name) && $deletion_allowed) { ?>
  <div class="row" style="margin-top: 20px;">
    <em><?php echo $this->lang->line('delete_stocking_place_ok_start').$short." (".$name.")".$this->lang->line('delete_stocking_place_ok_end'); ?></em>
  </div>
  <div class="btn-group row" style="margin-top: 10px;">
    <a href="<?php echo base_url().uri_string()."/confirmed";?>" class="btn btn-danger btn-lg"><?php echo $this->lang->line('text_yes'); ?></a>
    <a href="<?php echo base_url()."admin/view_stocking_places/";?>" class="btn btn-lg"><?php echo $this->lang->line('text_no'); ?></a>
  </div>
    <?php } else { ?>
    <em>Le lieu de stockage <?php echo $short; ?> (<?php echo $name; ?>) est utilisé sur un ou plusieurs objets et ne peut pas être supprimer.</em>
    <?php } ?>
</div>

<script src="<?php echo base_url(); ?>assets/js/geoline.js">
</script>