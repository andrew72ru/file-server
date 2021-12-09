File uploading and downloading service
======================================

You can use this as microservice that provided interface to file storage. Main features:

- Uploading files by chunks. See [example](#js-for-chunk-upload)
- file streaming. Affects only video content, another type of files not affected. When you upload `mp4` video, you 
  can use `<video>` tag to stream it in web-page. Example:
    ```html
    <video id="video" controls preload="auto" width="1280" height="720" poster="">
      <source src="https://127.0.0.1:8000/download/video/Interstellar.mp4" type='video/mp4' />
    </video>
    ```
- [NelmioCorsBundle](https://github.com/nelmio/NelmioCorsBundle) integrated: env variable `CORS_ALLOW_ORIGIN` for 
  Regex with hostname(s) which allowed to upload files
- [OneupFlysystemBundle](https://github.com/1up-lab/OneupFlysystemBundle) integrated: you can use any features to 
  define a way to store files.

## Set up and run

Go to project directory and build image

```shell
docker build -t files-service-dev:latest -f Dockerfile .
```

After that, you can run it

```shell
docker run -it --rm \
  -e APP_ENV=prod \ # Requred. If environment is not `prod`, you have to run `composer install` inside container
  -e CORS_ALLOW_ORIGIN='^https?://(localhost)(:[0-9]+)?$' \ # Allow you host to upload files
  -p 8000:8000 \ # Port to access if you run the web-server inside container
  -p 9000:9000 \ # Port to access to php-fpm
  files-service-dev:latest
```

## Configuration example

You have to define your own credentials in `.env.local` file. See `config/aws.yaml` to know about variables.

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true

  Aws\S3\S3ClientInterface:
    class: Aws\S3\S3Client
    arguments:
      -
        version: 'latest'
        region: '%env(S3_REGION)%'
        use_path_style_endpoint: '%env(bool:S3_PATH_STYLE)%'
        endpoint: '%env(S3_ENDPOINT)%'
        credentials:
          key: '%env(AWS_KEY)%'
          secret: '%env(AWS_SECRET)%'
```

You can define your own configuration for filesystem:

```yaml
oneup_flysystem:
  adapters:
    image.adapter:
      awss3v3:
        client: images.s3_client
        bucket: ~
        prefix: images
    video.adapter:
      awss3v3:
        client: images.s3_client
        bucket: ~
        prefix: video
  filesystems:
    image.filesystem:
      adapter: image.adapter
    video.filesystem:
      adapter: video.adapter
```

**Attention**: current implementation works with S3, Local and in-memory adapters. Feel free to improve. 

-----------------------

## JS for chunk upload

```javascript
(() => {
    const f = document.getElementById('f')
    if (f.files.length > 0) process()

    const process = async (e) => {
      const uuid = `my-unique-file-name-${new Date().toISOString()}`
      const f = e.target
      const file = f.files[0]
      const size = file.size
      const chunkSize = 1024 * 1024 // More than 1Mb

      const count = Math.ceil(size / chunkSize);
      for (let i = 0; i < count; i++) {
        let from = chunkSize * i;
        let piece = file.slice(from, (from + chunkSize), file.type);

        const fd = new FormData();
        // This is a required attributes! See App\Service\FileChunk
        fd.append('upload', piece);                   // File part
        fd.append('_chunkSize', chunkSize + '');      // Common size of chunk
        fd.append('_currentChunkSize', piece.size);   // Current chunk size
        fd.append('_chunkNumber', i + '');            // Chunk number
        fd.append('_totalSize', size + '');           // Total file size
        fd.append('_uniqueId', uuid);                 // Unique name for file. Be careful — only ASCII symbols, if file with this name (and type) exists, it will be override
        fd.append('type', 'video');                   // Type of service. Must be the same as App\Service\Handler\HandlerInterface::getName() method result

        await window.fetch('https://127.0.0.1:8000/upload', {
          method: 'POST',
          body: fd,
        }).then(r => r.json()).then((data) =>{
        /**
         * Response is JSON
         * { done: 14, file: filename.ext }
         * 'done' property contains percents from start, and 'file' contains null or full file path (on upload complete)
         **/
          if (data.hasOwnProperty('done'))
            document.getElementById('percent').innerText = `${data.done}%`; // Feel free to show progress bar or something else

          if (data.hasOwnProperty('file') && data.file !== null)
            document.getElementById('filename').innerText = data.file;
        });
      }
    }

    f.addEventListener('change', process, false)
  })()
```
