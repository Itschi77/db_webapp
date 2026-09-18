FROM debian:13-slim

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends samba smbclient \
    && rm -rf /var/lib/apt/lists/*

COPY smb.conf /etc/samba/smb.conf

EXPOSE 139 445

CMD ["smbd","--foreground","--no-process-group","--debug-stdout"]
