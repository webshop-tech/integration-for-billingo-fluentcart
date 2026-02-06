rm integration-for-billingo-fluentcart.zip
cd ..
zip integration-for-billingo-fluentcart/integration-for-billingo-fluentcart.zip -r integration-for-billingo-fluentcart \
   --exclude="integration-for-billingo-fluentcart/.git/*" \
   --exclude="integration-for-billingo-fluentcart/.env" \
   --exclude="integration-for-billingo-fluentcart/.gitignore" \
   --exclude="integration-for-billingo-fluentcart/tests/*" \
   --exclude="integration-for-billingo-fluentcart/assets/*" \
   --exclude="integration-for-billingo-fluentcart/*.zip" \
   --exclude="integration-for-billingo-fluentcart/*.md" \
   --exclude="integration-for-billingo-fluentcart/*.sh"
cd integration-for-billingo-fluentcart