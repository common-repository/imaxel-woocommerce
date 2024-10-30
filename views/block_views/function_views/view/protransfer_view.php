<div id="protransfer">

  <div id="file-uploader-box">

    <div id="file-uploader-box-content">

        <div v-if="!files.length">

            <label :for="name">

              <div id="file-uploader-box-upload-button">

                <div id="file-uploader-box-upload-button-content">
                  <span style="margin-bottom: 5px;" class="fa-stack">
                    <i class="fas fa-circle fa-stack-2x icon-background"></i>
                    <i style="color:white;"class="fas fa-plus fa-stack-1x"></i>
                  </span>
                  <p><?php echo __('Select your files','imaxel');?></p>
                </div>

              </div>

            </label>

        </div>

        <div v-else="!files.length">
          <div class="files-list-headers">
            <div class="item-name"><?php echo __('Name','imaxel')?></div>
            <div class="item-size"><?php echo __('Size','imaxel')?></div>
            <div class="item-action"><?php echo __('Action','imaxel')?></div>
          </div>
          <div class="files-item" v-for="file in files">
            <span class="item-name">{{file.name}}</span><span class="item-size">{{file.size | formatSize}} bytes</span><span class="item-action"><i title="<?php echo __('Remove','imaxel')?>" @click.prevent="$refs.upload.remove(file)" class="fas fa-times"></i></span>
          </div>
        </div>

    </div>

  </div>

  <file-upload
    :multiple="true"
    :drop="false"
    ref="upload"
    v-model="files"
    post-action="/post.method"
    put-action="/put.method"
    @input-file="inputFile"
    @input-filter="inputFilter"
  >

  <button class="admin_button" type="button"><?php echo '<i class="fas fa-plus"></i> '.__('Add more files','imaxel');?></button>

  </file-upload>

  <button v-show="!$refs.upload || !$refs.upload.active" @click.prevent="$refs.upload.active = true" type="button" class="admin_button">Start upload</button>
  <button v-show="$refs.upload && $refs.upload.active" @click.prevent="$refs.upload.active = false" type="button" class="admin_button">Stop upload</button>

</div>

<style>

  /*FILE UPLOADER BOX */
  #file-uploader-box {
    width: 100%;
    border: 1px solid #e1e1e1;
    margin-bottom: 15px;
    padding: 35px;
  }

  #file-uploader-box-content {
    border: 1px solid #e1e1e1;
    padding: 15px 15px;
  }

  #file-uploader-box-upload-button {
    text-align:center;
    cursor:pointer;
    transition: 0.3s all;
    border-radius: 4.5px;
  }

  #file-uploader-box-upload-button-content {
    padding-top: 35px;
    padding-bottom: 20px;
    transition: 0.3s all;
  }

  #file-uploader-box-upload-button-content:hover {
    background-color: #e1e1e1;
    transition: 0.3s all;
  }

  #file-uploader-box-upload-button-content p {
    font-size: 16px;
    font-weight: 600;
  }

  .icon-background {
    color: <?php echo $primaryColor;?>;
  }

  /*FILE ITEMS LIST*/
  .files-list-headers {
    display: flex;
    font-weight: 600;
    font-size: 15px;
    padding: 0px 10px 2.5px 10px;
    border-bottom: 1px solid #ccc;
  }

  .item-name {
    flex: 0.65;
  }
  .item-size {
    flex: 0.25;
  }

  .item-action {
    flex: 0.10;
    text-align: right;
  }

  .files-item {
    border-bottom: 1px solid #e1e1e1;
    padding: 3px 15px 3px 15px;
    font-size: 15px;
    font-size: 14px;
    display:flex;
  }


  .files-item i {
    float: right;
    margin-top: 8px;
    color: red;
  }

  .files-item:nth-child(even) {
    background-color: #e1e1e1;
  }

  /*FOOTER BUTTONS ACTIONS */
  .file-uploads label {
    cursor: pointer;
  }

</style>
