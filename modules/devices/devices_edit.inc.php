<?php
/*
* @version 0.1 (wizard)
*/
  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }
  $table_name='devices';
  $rec=SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");


  if ($this->tab=='logic') {
      $object=getObject($rec['LINKED_OBJECT']);
      $method_id=$object->getMethodByName('logicAction',$object->class_id,$object->id);

      $method_rec=SQLSelectOne("SELECT * FROM methods WHERE ID=".(int)$method_id);

      if ($method_rec['OBJECT_ID']!=$object->id) {
          $method_rec=array();
          $method_rec['OBJECT_ID']=$object->id;
          $method_rec['TITLE']='logicAction';
          $method_rec['ID']=SQLInsert('methods',$method_rec);
      }
      if ($this->mode=='update') {
          global $code;
          $method_rec['CODE']=$code;

          $ok=1;
          if ($method_rec['CODE']!='') {
              //echo $content;
              $errors=php_syntax_error($method_rec['CODE']);
              if ($errors) {
                  $out['ERR_CODE']=1;
                  $out['ERRORS']=nl2br($errors);
                  $ok=0;
              }
          }
          if ($ok) {
              SQLUpdate('methods',$method_rec);
              $out['OK']=1;
          } else {
              $out['ERR']=1;
          }
      }
      $out['CODE']=htmlspecialchars($method_rec['CODE']);
      $out['OBJECT_ID']=$method_rec['OBJECT_ID'];

      $parent_method_id=$object->getMethodByName('logicAction',$object->class_id,0);
      if ($parent_method_id) {
          $out['METHOD_ID']=$parent_method_id;
      } else {
          $out['METHOD_ID']=$method_rec['ID'];
      }

  }

  if ($this->tab=='settings') {
     $properties=$this->getAllProperties($rec['TYPE']);
      //print_r($properties);exit;
     if ($rec['LINKED_OBJECT'] && is_array($properties)) {
         $res_properties=array();

         foreach($properties as $k=>$v) {
             if ($v['_CONFIG_TYPE']) {
                 if ($this->mode=='update') {
                     global ${$k.'_value'};
                     if (isset(${$k.'_value'})) {
                      setGlobal($rec['LINKED_OBJECT'].'.'.$k,trim(${$k.'_value'}));
                     }
                     $out['OK']=1;
                 }
                 $v['NAME']=$k;
                 $v['CONFIG_TYPE']=$v['_CONFIG_TYPE'];
                 $v['VALUE']=getGlobal($rec['LINKED_OBJECT'].'.'.$k);
                 $res_properties[]=$v;
             }
         }
         //print_r($res_properties);exit;
         $out['PROPERTIES']=$res_properties;
     }
  }

  if ($this->tab=='interface') {
      if ($this->mode=='update') {
          global $add_menu;
          global $add_menu_id;

          global $add_scene;
          global $add_scene_id;

          if (!$add_scene) {
              $add_scene_id=0;
          }
          if (!$add_scene_id) {
              $add_scene=0;
          }

          $out['ADD_MENU']=$add_menu;
          $out['ADD_MENU_ID']=$add_menu_id;
          $out['ADD_SCENE']=$add_scene;
          $out['ADD_SCENE_ID']=$add_scene_id;

          if ($out['ADD_MENU']) {
              $this->addDeviceToMenu($rec['ID'],$add_menu_id);
          }

          if ($out['ADD_SCENE'] && $out['ADD_SCENE_ID']) {
              $this->addDeviceToScene($rec['ID'],$add_scene_id);
          }

          $out['OK']=1;
      }

      $out['SCENES']=SQLSelect("SELECT ID,TITLE FROM scenes ORDER BY TITLE");
      $menu_items=SQLSelect("SELECT ID, TITLE FROM commands ORDER BY PARENT_ID,TITLE");
      $res_items=array();
      $total = count($menu_items);
      for ($i = 0; $i < $total; $i++) {
          $sub=SQLSelectOne("SELECT ID FROM commands WHERE PARENT_ID=".$menu_items[$i]['ID']);
          if ($sub['ID']) {
              $res_items[]=$menu_items[$i];
          }
      }
      $out['MENU']=$res_items;

  }


  if ($this->tab=='') {
      global $source_table;
      $out['SOURCE_TABLE']=$source_table;
      global $source_table_id;
      $out['SOURCE_TABLE_ID']=$source_table_id;
      global $type;
      $out['TYPE']=$type;
      global $linked_object;
      $out['LINKED_OBJECT']=trim($linked_object);
      if ($out['LINKED_OBJECT'] && !$rec['ID']) {
          $old_rec=SQLSelectOne("SELECT * FROM devices WHERE LINKED_OBJECT LIKE '".DBSafe($out['LINKED_OBJECT'])."'");
          if ($old_rec['ID']) {
              $rec=$old_rec;
          }
      }
  }

  if ($this->mode=='update' && $this->tab=='') {
   $ok=1;
   global $title;
   $rec['TITLE']=$title;
   if ($rec['TITLE']=='') {
    $out['ERR_TITLE']=1;
    $ok=0;
   }

   $rec['TYPE']=$type;
   if ($rec['TYPE']=='') {
    $out['ERR_TYPE']=1;
    $ok=0;
   }

      global $location_id;
      $rec['LOCATION_ID']=(int)$location_id;

    $rec['LINKED_OBJECT']=$linked_object;

      global $add_object;
      $out['ADD_OBJECT']=$add_object;
      if ($add_object) {
          $rec['LINKED_OBJECT']='';
      }
      

  //UPDATING RECORD
   if ($ok) {

    $this->renderStructure();

    if ($rec['ID']) {
     SQLUpdate($table_name, $rec); // update
    } else {
     $new_rec=1;
     $rec['ID']=SQLInsert($table_name, $rec); // adding new record
     $added=1;
    }

    if ($rec['LOCATION_ID']) {
        $location_title=getRoomObjectByLocation($rec['LOCATION_ID'],1);
    }

    $out['OK']=1;

       if (!$rec['LINKED_OBJECT'] && $out['ADD_OBJECT']) {
           $type_details=$this->getTypeDetails($rec['TYPE']);
           $new_object_title=ucfirst($rec['TYPE']).$this->getNewObjectIndex($type_details['CLASS']);
           $object_id=addClassObject($type_details['CLASS'],$new_object_title,'sdevice'.$rec['ID']);
           $rec['LINKED_OBJECT']=$new_object_title;
           SQLUpdate('devices',$rec);
       }

       $object_id=addClassObject($type_details['CLASS'],$rec['LINKED_OBJECT']);

       $object_rec=SQLSelectOne("SELECT * FROM objects WHERE ID=".$object_id);
       $object_rec['DESCRIPTION']=$rec['TITLE'];
       $object_rec['LOCATION_ID']=$rec['LOCATION_ID'];
       SQLUpdate('objects',$object_rec);

       if ($location_title) {
           setGlobal($object_rec['TITLE'].'.linkedRoom',$location_title);
       }

       if ($added && $rec['TYPE']=='sensor_temp') {
           setGlobal($object_rec['TITLE'].'.minValue',16);
           setGlobal($object_rec['TITLE'].'.maxValue',25);
       }
       if ($added && $rec['TYPE']=='sensor_humidity') {
           setGlobal($object_rec['TITLE'].'.minValue',30);
           setGlobal($object_rec['TITLE'].'.maxValue',60);
       }

    if ($out['SOURCE_TABLE'] && $out['SOURCE_TABLE_ID']) {
        $this->addDeviceToSourceTable($out['SOURCE_TABLE'], $out['SOURCE_TABLE_ID'], $rec['ID']);
    }

    if ($added) {
      $this->redirect("?view_mode=edit_devices&id=".$rec['ID']."&tab=interface");
    }


   } else {
    $out['ERR']=1;
   }
  }
  if (is_array($rec)) {
   foreach($rec as $k=>$v) {
    if (!is_array($v)) {
     $rec[$k]=htmlspecialchars($v);
    }
   }
  }
  outHash($rec, $out);


  $types=array();
  foreach($this->device_types as $k=>$v) {
      if ($v['TITLE']) {
          $types[]=array('NAME'=>$k,'TITLE'=>$v['TITLE']);
      }
  }


if ($rec['LINKED_OBJECT']) {
    $processed=$this->processDevice($rec['ID']);
    $out['HTML']=$processed['HTML'];
}

  $out['TYPES']=$types;

$out['LOCATIONS']=SQLSelect("SELECT ID, TITLE FROM locations ORDER BY TITLE");