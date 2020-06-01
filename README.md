Сервис загрузки и раздачи файлов
================================

Вы можете использовать этот сервис для обеспечения хранения (и скачивания) файлов в вашем проекте. Основные фичи:

- загрузка файлов по частям. Вам необходимо обеспечить верную процедуру загрузки, чтобы использовать этот сервис (см. ниже);
- потоковая отдача файлов. В основном это пригодится для видео-контента, на другие документы (или изображения) не повлияет. Видео mp4, загруженное в этот сервис, может быть встроено на веб-страницу с помощью тега `<video>`. Например    
    ```html
    <video id="video" controls preload="auto" width="1280" height="720" poster="">
      <source src="https://127.0.0.1:8000/download/video/Interstellar.mp4" type='video/mp4' />
    </video>
    ```
- интеграция с [NelmioCorsBundle](https://github.com/nelmio/NelmioCorsBundle): при запуске контейнера (или настройке приложения) укажите хост, с которого будут приниматься запросы на загрузку файлов;
- интеграция с [OneupFlysystemBundle](https://github.com/1up-lab/OneupFlysystemBundle): вы можете использовать конфигурацю по-умолчанию или определить свою (см. ниже).

## Запуск контейнера

Запуск из командной строки:

```shell script
docker run -it --rm \
  -e APP_ENV=prod \ # Обязательно укажите окружение. Если окружение не prod, следует сначала выполнить composer install внутри контейнера
  -e CORS_ALLOW_ORIGIN='^https?://(you-host\.local)(:[0-9]+)?$' \ # доступ с вашего хоста
  -p 9000:9000 \ # порт для php-fpm
  -v /you/images/folder:/var/www/app/var/cache/prod/images # при конфигурации по-умолчанию это каталог с изображениями
  -v /you/video/folder:/var/www/app/var/cache/prod/video # при конфигурации по-умолчанию это каталог с видеофайлами
  git.crtweb.ru:4567/rostelecom/files-service/app/prod:latest
```

Внедрение в docker-compose

```yaml
  services:
    files:
      image: git.crtweb.ru:4567/rostelecom/files-service/app/prod:latest
      ports:
        - 9000:9000
      volumes:
        - /you/images/folder:/var/www/app/var/cache/prod/images
        - /you/video/folder:/var/www/app/var/cache/prod/video
```

## Запуск отдельного приложения

Вы можете клонировать этот репозиторий и запустить сервис с собственной конфигурацией. Все действия и настройки — стандартные для приложения на Symfony5 и php 7.4.5.

## Конфигурация OneupFlysystemBundle

По-умолчанию (в собранном контейнере) конфигурация хранения файлов настроена на локальное хранилище. Полный файл конфигурации `config/packages/oneup_flysystem.yaml`:

```yaml
oneup_flysystem:
  adapters:
    image.adapter:
      local:
        directory: '%kernel.cache_dir%/images'
    video.adapter:
      local:
        directory: '%kernel.cache_dir%/video'
  filesystems:
    image.filesystem:
      adapter: image.adapter
    video.filesystem:
      adapter: video.adapter
```

Обратите внимание, что на динамически созданные сервисы ориентируется контейнер зависимостей (настройки в `services.yaml`):

```yaml
services:

  file_handler.image:
    class: App\Service\Handler\ImageHandler
    arguments:
      - '@oneup_flysystem.image.filesystem_filesystem' # Это экзепляр сервиса, созданного в filesystems.image.filesystem
    tags: { name: 'file.handler' }

  file_handler.video:
    class: App\Service\Handler\VideoHandler
    arguments:
      - '@oneup_flysystem.video.filesystem_filesystem' # Это экземпляр сервиса, созданного в filesystems.video.filesystem
    tags: { name: 'file.handler' }

  App\Service\FileReceiverInterface:
    class: App\Service\FileReceiver
    arguments:
      - ['@file_handler.video', '@file_handler.image'] # Сервис, принимающий файлы, должен получать все сервисы фс

  App\Controller\FileAccess\:
    resource: '../src/Controller/FileAccess'
    arguments:                                         # Контроллеры в этом нэйсмспесе должны получать ассоциативный массив с ключами, которые будут идентифицировать путь и значениями — экземплярами сервисов.
      - images: '@oneup_flysystem.image.filesystem_filesystem'
        video: '@oneup_flysystem.video.filesystem_filesystem'

```

Соответственно, если вы просто переопределяете два сервиса (для изображений и видео) на, к примеру, AWS, вы должны сделать файл конфигурации, который подменит `config/packages/oneup_flysystem.yaml`:

```yaml
services:
  images.s3_client:
    class: Aws\S3\S3Client
    arguments:
      -
        version: '2006-03-01' # or 'latest'
        region: "region-id" # 'eu-central-1' for example
        credentials:
          key: "s3-key"
          secret: "s3-secret"
  video.s3_client:
    class: Aws\S3\S3Client
    arguments:
      -
        version: '2006-03-01' # or 'latest'
        region: "region-id" # 'eu-central-1' for example
        credentials:
          key: "s3-key"
          secret: "s3-secret"

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

Таким образом всё будет работать не с локальной ФС, а с удаленным S3-бакетом.

> **Примечание**
> 
> Скорее всего, так легко всё не заработает, потому что почти для каждой имплементации ФС нужен [собственный пакет](https://github.com/1up-lab/OneupFlysystemBundle/blob/master/Resources/doc/index.md), и поэтому придется пересобрать контейнер.    
> Однако, конфигурация сборки production-контейнера тривиальна (файл `production.Dockerfile`) и не вызовет сложностей.

После того, как создана такая конфигурация, вы можете примонтировать ваш файл к контейнеру:

```yaml
  services:
    files:
      image: git.crtweb.ru:4567/rostelecom/files-service/app/prod:latest
      ports:
        - 9000:9000
      volumes:
        - /you/images/folder:/var/www/app/var/cache/prod/images
        - /you/video/folder:/var/www/app/var/cache/prod/video
        - /you/path/to/oneup_flysystem.yaml:/var/www/app/config/packages/oneup_flysystem.yaml
```

Естественно, если вы переопределяете названия ФС, добавляете отдельные ФС и прочее, вам понадобится внести изменения также в файл `services.yaml`

## Просмотр, скачивание

Роут `/download/{type}/{filename}` отвечает за скачивание файлов. `type` — ключ для ФС, ключи определены в конфигурации контроллера:

```yaml
App\Controller\FileAccess\:
    resource: '../src/Controller/FileAccess'
    arguments:
      - images: '@oneup_flysystem.image.filesystem_filesystem' # ключ images будет указывать на ФС с картинками
        video: '@oneup_flysystem.video.filesystem_filesystem'  # ключ video — на ФС с видео
```

Соответственно, скачать (посмотреть) файл с названием `977f1a27-fd9b-4519-9038-e11f93eed1ba.jpg` можно по адресу `/download/images/977f1a27-fd9b-4519-9038-e11f93eed1ba.jpg`

Вы можете встраивать такие адреса в `src` изображений, или в `source src` video-тэгов.

## Удаление файлов

Роут `/delete/{type}/{filename}` отвечает за удаление файлов. Вы должны определить секретный ключ для сервиса и отправить его в заголовке `X-Security-Token`. Не рекомендуется удалять файлы из фронтенд-части (секретный ключ пока что просто строка, соответственно, любой, узнавший этот ключ, сможет удалить любые известные ему файлы), лучше делать это со стороны серверной части вашего приложения. 

В будущем планируется сделать процедуру удаления и загрузки более безопасной.

## Пример фронтенд-скрипта загрузки картинки

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

## Дополнения

### Команды для сборки образов локально

```shell script
docker build -t git.crtweb.ru:4567/rostelecom/files-service/app/dev:latest -f Dockerfile .
docker build --build-arg CI_REGISTRY_IMAGE='git.crtweb.ru:4567/rostelecom/files-service' -t git.crtweb.ru:4567/rostelecom/files-service/app/prod:latest -f production.Dockerfile .
```

Как обычно, можно свободно забирать, распространять, дарить, продавать и делать, что хочешь с этим кодом. Пулл-реквесты **приветствуются**, в том числе для **тестов контроллера стриминга** `App\Controller\FileAccess\DownloadController`.

### Демо-файлы

Для проверки работы сервиса независимо от внешних хранилищ (и наличия файлов в них) существуют два домонстрационных роута:

- изображение: `download/images/image-demo-775fdA.jpg`
- видео: `download/video/video-demo-74df86.mp4`

Эти адреса можно использовать для проверки работоспособности сервиса.
