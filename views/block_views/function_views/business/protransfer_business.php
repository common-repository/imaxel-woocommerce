<script>

    //taken from https://github.com/lian-yue/vue-upload-component
    //taken from https://lian-yue.github.io/vue-upload-component

    Vue.component('file-upload', VueUploadComponent);
    new Vue({
        el: '#protransfer',
        data: function () {
            return {
                files: [],
                name: 'file'
            }
        },
        components: {
            FileUpload: VueUploadComponent
        },
        methods: {

            /**
             * Has changed
             * @param  Object|undefined   newFile   Read only
             * @param  Object|undefined   oldFile   Read only
             * @return undefined
             */
            inputFile: function (newFile, oldFile) {

                if (newFile && oldFile && !newFile.active && oldFile.active) {

                    // Get response data
                    console.log('response', newFile.response)

                    if (newFile.xhr) {

                    // Get the response status code
                    console.log('status', newFile.xhr.status)
                    }
                }
            },

            /**
             * Pretreatment
             * @param  Object|undefined   newFile   Read and write
             * @param  Object|undefined   oldFile   Read only
             * @param  Function           prevent   Prevent changing
             * @return undefined
             */
            inputFilter: function (newFile, oldFile, prevent) {
                if (newFile && !oldFile) {
                    // Filter non-image file
                    if (!/\.(jpeg|jpe|jpg|gif|png|webp)$/i.test(newFile.name)) {
                    return prevent()
                    }
                }

                // Create a blob field
                newFile.blob = ''
                let URL = window.URL || window.webkitURL
                if (URL && URL.createObjectURL) {
                    newFile.blob = URL.createObjectURL(newFile.file)
                }
            }

        }
    });
</script>